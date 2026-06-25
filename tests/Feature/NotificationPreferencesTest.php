<?php

use App\Models\Plan;
use App\Models\User;

beforeEach(function () {
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
});

test('guest cannot update notification preferences', function () {
    $response = $this->put('/user/notification-preferences', [
        'notify_secret_retrieved' => true,
    ]);

    $response->assertRedirect('/login');
});

test('user can enable secret retrieved notification', function () {
    $user = User::factory()->create();
    subscribeUserToPlan($user, $this->basicPlan);
    $this->actingAs($user);

    $response = $this->put('/user/notification-preferences', [
        'notify_secret_retrieved' => true,
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->notify_secret_retrieved)->toBeTrue();
});

test('user can disable secret retrieved notification', function () {
    $user = User::factory()->withSecretRetrievedNotifications()->create();
    subscribeUserToPlan($user, $this->basicPlan);
    $this->actingAs($user);

    $response = $this->put('/user/notification-preferences', [
        'notify_secret_retrieved' => false,
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->notify_secret_retrieved)->toBeFalse();
});

test('validation requires boolean', function () {
    $user = User::factory()->create();
    subscribeUserToPlan($user, $this->basicPlan);
    $this->actingAs($user);

    $response = $this->put('/user/notification-preferences', [
        'notify_secret_retrieved' => 'not-a-boolean',
    ]);

    $response->assertSessionHasErrors('notify_secret_retrieved');
});

test('user without email plan cannot update notification preferences', function () {
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
    subscribeUserToPlan($user, $freePlan);
    $this->actingAs($user);

    $response = $this->put('/user/notification-preferences', [
        'notify_secret_retrieved' => true,
    ]);

    $response->assertStatus(403);
});

test('notification preference cleared when plan downgraded', function () {
    $user = User::factory()->withSecretRetrievedNotifications()->create();
    subscribeUserToPlan($user, $this->basicPlan);

    expect($user->fresh()->notify_secret_retrieved)->toBeTrue();

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

    expect($user->fresh()->notify_secret_retrieved)->toBeFalse();
});

test('plan supports email notifications inertia prop is true for qualifying plan', function () {
    $user = User::factory()->create();
    subscribeUserToPlan($user, $this->basicPlan);

    $response = $this->actingAs($user)->get(route('profile.show'));

    $response->assertInertia(fn ($page) => $page->where('auth.planSupportsEmailNotifications', true));
});

test('plan supports email notifications inertia prop is false without plan', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.show'));

    $response->assertInertia(fn ($page) => $page->where('auth.planSupportsEmailNotifications', false));
});

test('notification preference cleared when subscription cancelled', function () {
    $user = User::factory()->withSecretRetrievedNotifications()->create();
    subscribeUserToPlan($user, $this->basicPlan);

    expect($user->fresh()->notify_secret_retrieved)->toBeTrue();

    $subscription = $user->subscriptions()->first();
    $subscription->update([
        'stripe_status' => 'canceled',
        'ends_at' => now(),
    ]);

    expect($user->fresh()->notify_secret_retrieved)->toBeFalse();
});
