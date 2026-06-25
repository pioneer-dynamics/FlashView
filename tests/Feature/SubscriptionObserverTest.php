<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

function subscribeUser(User $user, Plan $plan): Subscription
{
    return $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->bothify('??????????'),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
}

test('tokens deleted when subscription loses api access', function () {
    $apiPlan = Plan::factory()->withApiAccess()->create();
    $freePlan = Plan::factory()->free()->create();
    $user = User::factory()->withPersonalTeam()->create();

    $subscription = subscribeUser($user, $apiPlan);
    $user->createToken('test-token', ['secrets:list']);
    expect($user->tokens)->toHaveCount(1);

    $subscription->update(['stripe_price' => $freePlan->stripe_monthly_price_id]);

    expect($user->fresh()->tokens)->toHaveCount(0);
});

test('webhook cleared when plan loses api access', function () {
    $apiPlan = Plan::factory()->withApiAccess()->create();
    $freePlan = Plan::factory()->free()->create();
    $user = User::factory()->withPersonalTeam()->withWebhook()->create();

    $subscription = subscribeUser($user, $apiPlan);

    $subscription->update(['stripe_price' => $freePlan->stripe_monthly_price_id]);

    $user->refresh();
    expect($user->webhook_url)->toBeNull();
    expect($user->webhook_secret)->toBeNull();
});

test('email notification reset when plan loses email support', function () {
    $emailPlan = Plan::factory()->withEmailNotifications()->create();
    $freePlan = Plan::factory()->free()->create();
    $user = User::factory()->withPersonalTeam()->withSecretRetrievedNotifications()->create();

    $subscription = subscribeUser($user, $emailPlan);
    expect($user->notify_secret_retrieved)->toBeTrue();

    $subscription->update(['stripe_price' => $freePlan->stripe_monthly_price_id]);

    expect($user->fresh()->notify_secret_retrieved)->toBeFalse();
});

test('tokens not deleted when plan retains api access', function () {
    $plan1 = Plan::factory()->withApiAccess()->create();
    $plan2 = Plan::factory()->withApiAccess()->create();
    $user = User::factory()->withPersonalTeam()->create();

    $subscription = subscribeUser($user, $plan1);
    $user->createToken('test-token', ['secrets:list']);

    $subscription->update(['stripe_price' => $plan2->stripe_monthly_price_id]);

    expect($user->fresh()->tokens)->toHaveCount(1);
});

test('subscription deletion clears everything', function () {
    $apiPlan = Plan::factory()->withApiAccess()->create();
    $user = User::factory()->withPersonalTeam()
        ->withWebhook()
        ->withSecretRetrievedNotifications()
        ->create();

    $subscription = subscribeUser($user, $apiPlan);
    $user->createToken('test-token', ['secrets:list']);

    $subscription->delete();

    $user->refresh();
    expect($user->tokens)->toHaveCount(0);
    expect($user->webhook_url)->toBeNull();
    expect($user->webhook_secret)->toBeNull();
    expect($user->notify_secret_retrieved)->toBeFalse();
});
