<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function payload(): array
{
    return ['message' => 'test secret', 'expires_in' => 5];
}

function subscribe(User $user, Plan $plan): void
{
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->word(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
}

function planWithThrottle(int $perMinute): Plan
{
    return Plan::factory()->create([
        'features' => [
            'messages' => ['order' => 1, 'type' => 'limit', 'config' => ['message_length' => 10000]],
            'expiry' => ['order' => 2, 'type' => 'limit', 'config' => ['expiry_minutes' => 1440]],
            'throttling' => ['order' => 3, 'type' => 'limit', 'config' => ['per_minute' => $perMinute]],
        ],
    ]);
}

function planWithoutThrottle(): Plan
{
    return Plan::factory()->create([
        'features' => [
            'messages' => ['order' => 1, 'type' => 'limit', 'config' => ['message_length' => 10000]],
            'expiry' => ['order' => 2, 'type' => 'limit', 'config' => ['expiry_minutes' => 1440]],
        ],
    ]);
}

test('subscribed user with throttling feature is rate limited', function () {
    $user = User::factory()->withPersonalTeam()->create();
    subscribe($user, planWithThrottle(perMinute: 2));

    $this->actingAs($user);

    $this->post(route('secret.store'), payload())->assertRedirect();
    $this->post(route('secret.store'), payload())->assertRedirect();
    $this->post(route('secret.store'), payload())->assertStatus(429);
});

test('throttle limit respects per minute value from plan', function () {
    $user = User::factory()->withPersonalTeam()->create();
    subscribe($user, planWithThrottle(perMinute: 4));

    $this->actingAs($user);

    for ($i = 0; $i < 4; $i++) {
        $this->post(route('secret.store'), payload())->assertRedirect();
    }

    $this->post(route('secret.store'), payload())->assertStatus(429);
});

test('subscribed user without throttling feature is not rate limited', function () {
    $user = User::factory()->withPersonalTeam()->create();
    subscribe($user, planWithoutThrottle());

    $this->actingAs($user);

    for ($i = 0; $i < 20; $i++) {
        $this->post(route('secret.store'), payload())->assertRedirect();
    }
});

test('unsubscribed user uses config rate limit', function () {
    $limit = config('secrets.rate_limit.user.per_minute');
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user);

    for ($i = 0; $i < $limit; $i++) {
        $this->post(route('secret.store'), payload())->assertRedirect();
    }

    $this->post(route('secret.store'), payload())->assertStatus(429);
});
