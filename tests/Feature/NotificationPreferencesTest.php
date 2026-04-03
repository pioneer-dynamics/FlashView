<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private Plan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic',
            'stripe_yearly_price_id' => 'price_yearly_basic',
            'stripe_product_id' => 'prod_basic',
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => [
                'email_notification' => [
                    'order' => 4.5,
                    'label' => 'Email Notifications',
                    'config' => ['email' => true],
                    'type' => 'feature',
                ],
                'webhook_notification' => [
                    'order' => 4.6,
                    'label' => 'Webhook Notifications',
                    'config' => ['webhook' => false],
                    'type' => 'missing',
                ],
            ],
        ]);
    }

    private function subscribeUserToPlan(User $user, Plan $plan): void
    {
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->word(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
    }

    public function test_guest_cannot_update_notification_preferences(): void
    {
        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_user_can_enable_secret_retrieved_notification(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserToPlan($user, $this->basicPlan);
        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->notify_secret_retrieved);
    }

    public function test_user_can_disable_secret_retrieved_notification(): void
    {
        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $this->subscribeUserToPlan($user, $this->basicPlan);
        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }

    public function test_validation_requires_boolean(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserToPlan($user, $this->basicPlan);
        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors('notify_secret_retrieved');
    }

    public function test_user_without_email_plan_cannot_update_notification_preferences(): void
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'stripe_monthly_price_id' => 'price_monthly_free',
            'stripe_yearly_price_id' => 'price_yearly_free',
            'stripe_product_id' => 'prod_free',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'email_notification' => [
                    'order' => 4.5,
                    'label' => 'Email Notifications',
                    'config' => ['email' => false],
                    'type' => 'missing',
                ],
                'webhook_notification' => [
                    'order' => 4.6,
                    'label' => 'Webhook Notifications',
                    'config' => ['webhook' => false],
                    'type' => 'missing',
                ],
            ],
        ]);

        $user = User::factory()->create();
        $this->subscribeUserToPlan($user, $freePlan);
        $this->actingAs($user);

        $response = $this->put('/user/notification-preferences', [
            'notify_secret_retrieved' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_notification_preference_cleared_when_plan_downgraded(): void
    {
        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $this->subscribeUserToPlan($user, $this->basicPlan);

        $this->assertTrue($user->fresh()->notify_secret_retrieved);

        $freePlan = Plan::factory()->create([
            'name' => 'Free Downgrade',
            'stripe_monthly_price_id' => 'price_monthly_free_downgrade',
            'stripe_yearly_price_id' => 'price_yearly_free_downgrade',
            'stripe_product_id' => 'prod_free_downgrade',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'email_notification' => [
                    'order' => 4.5,
                    'label' => 'Email Notifications',
                    'config' => ['email' => false],
                    'type' => 'missing',
                ],
                'webhook_notification' => [
                    'order' => 4.6,
                    'label' => 'Webhook Notifications',
                    'config' => ['webhook' => false],
                    'type' => 'missing',
                ],
            ],
        ]);

        $subscription = $user->subscriptions()->first();
        $subscription->update([
            'stripe_price' => $freePlan->stripe_monthly_price_id,
        ]);

        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }

    public function test_notification_preference_cleared_when_subscription_cancelled(): void
    {
        $user = User::factory()->withSecretRetrievedNotifications()->create();
        $this->subscribeUserToPlan($user, $this->basicPlan);

        $this->assertTrue($user->fresh()->notify_secret_retrieved);

        $subscription = $user->subscriptions()->first();
        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        $this->assertFalse($user->fresh()->notify_secret_retrieved);
    }
}
