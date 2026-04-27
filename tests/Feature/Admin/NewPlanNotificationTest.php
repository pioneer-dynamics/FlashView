<?php

namespace Tests\Feature\Admin;

use App\Jobs\NotifyUsersOfHigherValuePlan;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\NewHigherValuePlanNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewPlanNotificationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Controller-level tests — assert the job is dispatched on plan creation
    // -------------------------------------------------------------------------

    public function test_job_is_dispatched_when_admin_creates_a_plan(): void
    {
        Queue::fake();

        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->planPayload([
            'name' => 'Enterprise',
            'price_per_month' => 50.00,
        ]));

        Queue::assertPushed(NotifyUsersOfHigherValuePlan::class);
    }

    // -------------------------------------------------------------------------
    // Job-level tests — call handle() directly with Notification::fake() active
    // -------------------------------------------------------------------------

    public function test_subscribed_users_on_lower_value_plans_receive_notification(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

        Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class);
    }

    public function test_free_plan_users_receive_notification_when_new_plan_is_higher_value(): void
    {
        Notification::fake();

        Plan::factory()->free()->create(['price_per_month' => 0]);
        $newPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $freeUser = $this->verifiedUser();

        (new NotifyUsersOfHigherValuePlan($newPlan))->handle();

        Notification::assertSentTo($freeUser, NewHigherValuePlanNotification::class);
    }

    public function test_users_on_equal_value_plan_do_not_receive_notification(): void
    {
        Notification::fake();

        $existingPlan = Plan::factory()->create(['price_per_month' => 25.00]);
        $samePricePlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $existingPlan);

        (new NotifyUsersOfHigherValuePlan($samePricePlan))->handle();

        Notification::assertNotSentTo($subscriber, NewHigherValuePlanNotification::class);
    }

    public function test_users_on_higher_value_plan_do_not_receive_notification(): void
    {
        Notification::fake();

        $expensivePlan = Plan::factory()->create(['price_per_month' => 50.00]);
        $cheaperPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $expensivePlan);

        (new NotifyUsersOfHigherValuePlan($cheaperPlan))->handle();

        Notification::assertNotSentTo($subscriber, NewHigherValuePlanNotification::class);
    }

    public function test_suspended_users_are_excluded(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $suspended = User::factory()->withPersonalTeam()->create([
            'email_verified_at' => now(),
            'suspended_at' => now(),
        ]);
        $this->subscribeUserToPlan($suspended, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

        Notification::assertNotSentTo($suspended, NewHigherValuePlanNotification::class);
    }

    public function test_unverified_users_are_excluded(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $unverified = User::factory()->withPersonalTeam()->create([
            'email_verified_at' => null,
        ]);
        $this->subscribeUserToPlan($unverified, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

        Notification::assertNotSentTo($unverified, NewHigherValuePlanNotification::class);
    }

    public function test_email_contains_limited_offer_line_when_end_date_is_set(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $limitedPlan = Plan::factory()->create([
            'price_per_month' => 25.00,
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($limitedPlan))->handle();

        Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber, $limitedPlan) {
            $mail = $notification->toMail($subscriber);
            $lines = collect($mail->introLines)->implode(' ');

            return str_contains($lines, 'limited-time offer')
                && str_contains($lines, $limitedPlan->end_date->format('j F Y'));
        });
    }

    public function test_email_does_not_contain_limited_offer_line_when_end_date_is_null(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $openPlan = Plan::factory()->create([
            'price_per_month' => 25.00,
            'end_date' => null,
        ]);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($openPlan))->handle();

        Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
            $mail = $notification->toMail($subscriber);
            $lines = collect($mail->introLines)->implode(' ');

            return ! str_contains($lines, 'limited-time offer');
        });
    }

    public function test_email_subject_includes_plan_name(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $higherPlan = Plan::factory()->create(['price_per_month' => 25.00, 'name' => 'ProMax']);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

        Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
            $mail = $notification->toMail($subscriber);

            return str_contains($mail->subject, 'ProMax');
        });
    }

    public function test_email_greeting_includes_user_name(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
        $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $subscriber = $this->verifiedUser(['name' => 'Jane Doe']);
        $this->subscribeUserToPlan($subscriber, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

        Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
            $mail = $notification->toMail($subscriber);

            return str_contains($mail->greeting, 'Jane Doe');
        });
    }

    public function test_email_contextualises_against_users_current_plan(): void
    {
        Notification::fake();

        $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00, 'name' => 'Starter']);
        $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

        $subscriber = $this->verifiedUser();
        $this->subscribeUserToPlan($subscriber, $lowerPlan);

        (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

        Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
            $mail = $notification->toMail($subscriber);
            $lines = collect($mail->introLines)->implode(' ');

            return str_contains($lines, 'Starter');
        });
    }

    public function test_no_notification_sent_when_new_plan_is_the_cheapest(): void
    {
        Notification::fake();

        Plan::factory()->create(['price_per_month' => 25.00]);
        $cheapestPlan = Plan::factory()->create(['price_per_month' => 5.00]);

        $subscriber = $this->verifiedUser();

        (new NotifyUsersOfHigherValuePlan($cheapestPlan))->handle();

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Config::set('admin.emails', [$user->email]);

        return $user;
    }

    private function verifiedUser(array $attributes = []): User
    {
        return User::factory()->withPersonalTeam()->create(array_merge([
            'email_verified_at' => now(),
            'suspended_at' => null,
        ], $attributes));
    }

    private function subscribeUserToPlan(User $user, Plan $plan): void
    {
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.$user->id,
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Plan',
            'price_per_month' => 10.00,
            'price_per_year' => 100.00,
            'create_stripe_product' => false,
            'stripe_product_id' => '',
            'stripe_monthly_price_id' => '',
            'stripe_yearly_price_id' => '',
            'features' => [
                'messages' => ['order' => 1, 'type' => 'limit', 'config' => ['message_length' => 5000]],
                'expiry' => ['order' => 3, 'type' => 'limit', 'config' => ['expiry_minutes' => 20160]],
                'throttling' => ['order' => 4, 'type' => 'feature', 'config' => []],
                'support' => ['order' => 5, 'type' => 'limit', 'config' => ['support_type' => 'standard']],
                'api' => ['order' => 6, 'type' => 'feature', 'config' => []],
            ],
        ], $overrides);
    }
}
