<?php

use App\Models\Plan;
use App\Models\User;
use App\Rules\ValidExpiry;

function runValidExpiryValidation(string $userType, mixed $value): bool
{
    $rule = new ValidExpiry($userType);
    $passed = true;

    $rule->validate('expires_in', $value, function () use (&$passed) {
        $passed = false;
    });

    return $passed;
}

test('valid guest expiry passes', function () {
    expect(runValidExpiryValidation('guest', 5))->toBeTrue();
});

test('guest expiry exceeding limit fails', function () {
    $guestLimit = config('secrets.expiry_limits.guest');
    $beyondLimit = collect(config('secrets.expiry_options'))
        ->firstWhere(fn ($opt) => $opt['value'] > $guestLimit);

    if ($beyondLimit) {
        expect(runValidExpiryValidation('guest', $beyondLimit['value']))->toBeFalse();
    } else {
        $this->markTestSkipped('No expiry option exceeds guest limit.');
    }
});

test('valid user expiry passes', function () {
    $plan = Plan::factory()->free()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    expect(runValidExpiryValidation('user', 5))->toBeTrue();
});

test('user expiry exceeding free plan limit fails', function () {
    $plan = Plan::factory()->free()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    $planExpiryMinutes = $plan->features['expiry']['config']['expiry_minutes'];
    $beyondLimit = collect(config('secrets.expiry_options'))
        ->firstWhere(fn ($opt) => $opt['value'] > $planExpiryMinutes);

    if ($beyondLimit) {
        expect(runValidExpiryValidation('user', $beyondLimit['value']))->toBeFalse();
    } else {
        $this->markTestSkipped('No expiry option exceeds free plan limit.');
    }
});

test('invalid expiry value fails', function () {
    expect(runValidExpiryValidation('guest', 999))->toBeFalse();
});

test('all guest allowed options pass', function () {
    $guestLimit = config('secrets.expiry_limits.guest');
    $allowed = collect(config('secrets.expiry_options'))
        ->filter(fn ($opt) => $opt['value'] <= $guestLimit);

    foreach ($allowed as $option) {
        expect(runValidExpiryValidation('guest', $option['value']))
            ->toBeTrue("Expected expiry value {$option['value']} to pass for guest");
    }
});

test('subscribed user expiry uses plan limit', function () {
    $plan = Plan::factory()->withApiAccess()->create();
    $user = User::factory()->withPersonalTeam()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_expiry',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    $planExpiryMinutes = $plan->features['expiry']['config']['expiry_minutes'];
    $allowed = collect(config('secrets.expiry_options'))
        ->filter(fn ($opt) => $opt['value'] <= $planExpiryMinutes);

    foreach ($allowed as $option) {
        expect(runValidExpiryValidation('user', $option['value']))
            ->toBeTrue("Expected expiry value {$option['value']} to pass for subscribed user");
    }
});
