<?php

use App\Jobs\NotifyUsersOfHigherValuePlan;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\NewHigherValuePlanNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('job is dispatched when admin creates a plan', function () {
    Queue::fake();

    $admin = adminUser();

    $this->actingAs($admin)->postJson(route('admin.plans.store'), planPayload([
        'name' => 'Enterprise',
        'price_per_month' => 50.00,
    ]));

    Queue::assertPushed(NotifyUsersOfHigherValuePlan::class);
});

test('subscribed users on lower value plans receive notification', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

    Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class);
});

test('free plan users receive notification when new plan is higher value', function () {
    Notification::fake();

    Plan::factory()->free()->create(['price_per_month' => 0]);
    $newPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $freeUser = verifiedUser();

    (new NotifyUsersOfHigherValuePlan($newPlan))->handle();

    Notification::assertSentTo($freeUser, NewHigherValuePlanNotification::class);
});

test('users on equal value plan do not receive notification', function () {
    Notification::fake();

    $existingPlan = Plan::factory()->create(['price_per_month' => 25.00]);
    $samePricePlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $existingPlan);

    (new NotifyUsersOfHigherValuePlan($samePricePlan))->handle();

    Notification::assertNotSentTo($subscriber, NewHigherValuePlanNotification::class);
});

test('users on higher value plan do not receive notification', function () {
    Notification::fake();

    $expensivePlan = Plan::factory()->create(['price_per_month' => 50.00]);
    $cheaperPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $expensivePlan);

    (new NotifyUsersOfHigherValuePlan($cheaperPlan))->handle();

    Notification::assertNotSentTo($subscriber, NewHigherValuePlanNotification::class);
});

test('suspended users are excluded', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $suspended = User::factory()->withPersonalTeam()->create([
        'email_verified_at' => now(),
        'suspended_at' => now(),
    ]);
    subscribeUserToPlan($suspended, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

    Notification::assertNotSentTo($suspended, NewHigherValuePlanNotification::class);
});

test('unverified users are excluded', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $unverified = User::factory()->withPersonalTeam()->create([
        'email_verified_at' => null,
    ]);
    subscribeUserToPlan($unverified, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

    Notification::assertNotSentTo($unverified, NewHigherValuePlanNotification::class);
});

test('email contains limited offer line when end date is set', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $limitedPlan = Plan::factory()->create([
        'price_per_month' => 25.00,
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($limitedPlan))->handle();

    Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber, $limitedPlan) {
        $mail = $notification->toMail($subscriber);
        $lines = collect($mail->introLines)->implode(' ');

        return str_contains($lines, 'limited-time offer')
            && str_contains($lines, $limitedPlan->end_date->format('j F Y'));
    });
});

test('email does not contain limited offer line when end date is null', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $openPlan = Plan::factory()->create([
        'price_per_month' => 25.00,
        'end_date' => null,
    ]);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($openPlan))->handle();

    Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
        $mail = $notification->toMail($subscriber);
        $lines = collect($mail->introLines)->implode(' ');

        return ! str_contains($lines, 'limited-time offer');
    });
});

test('email subject includes plan name', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $higherPlan = Plan::factory()->create(['price_per_month' => 25.00, 'name' => 'ProMax']);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

    Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
        $mail = $notification->toMail($subscriber);

        return str_contains($mail->subject, 'ProMax');
    });
});

test('email greeting includes user name', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00]);
    $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $subscriber = verifiedUser(['name' => 'Jane Doe']);
    subscribeUserToPlan($subscriber, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

    Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
        $mail = $notification->toMail($subscriber);

        return str_contains($mail->greeting, 'Jane Doe');
    });
});

test('email contextualises against users current plan', function () {
    Notification::fake();

    $lowerPlan = Plan::factory()->create(['price_per_month' => 10.00, 'name' => 'Starter']);
    $higherPlan = Plan::factory()->create(['price_per_month' => 25.00]);

    $subscriber = verifiedUser();
    subscribeUserToPlan($subscriber, $lowerPlan);

    (new NotifyUsersOfHigherValuePlan($higherPlan))->handle();

    Notification::assertSentTo($subscriber, NewHigherValuePlanNotification::class, function ($notification) use ($subscriber) {
        $mail = $notification->toMail($subscriber);
        $lines = collect($mail->introLines)->implode(' ');

        return str_contains($lines, 'Starter');
    });
});

test('no notification sent when new plan is the cheapest', function () {
    Notification::fake();

    Plan::factory()->create(['price_per_month' => 25.00]);
    $cheapestPlan = Plan::factory()->create(['price_per_month' => 5.00]);

    $subscriber = verifiedUser();

    (new NotifyUsersOfHigherValuePlan($cheapestPlan))->handle();

    Notification::assertNothingSent();
});

// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------
function verifiedUser(array $attributes = []): User
{
    return User::factory()->withPersonalTeam()->create(array_merge([
        'email_verified_at' => now(),
        'suspended_at' => null,
    ], $attributes));
}
