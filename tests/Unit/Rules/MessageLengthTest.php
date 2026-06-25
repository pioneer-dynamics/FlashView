<?php

use App\Models\Plan;
use App\Models\User;
use App\Rules\MessageLength;

function makeEncryptedMessage(int $plaintextLength): string
{
    $salt = str_repeat('a', 16);
    $header = str_repeat("\0", 28);
    $plaintext = str_repeat('x', $plaintextLength);

    return $salt.base64_encode($header.$plaintext);
}

function runMessageLengthValidation(string $userType, string $message, int $minLength = 1): bool
{
    $rule = new MessageLength($userType, $minLength);
    $passed = true;

    $rule->validate('message', $message, function () use (&$passed) {
        $passed = false;
    });

    return $passed;
}

test('guest message within limit passes', function () {
    $limit = config('secrets.message_length.guest');
    $message = makeEncryptedMessage($limit - 10);

    expect(runMessageLengthValidation('guest', $message))->toBeTrue();
});

test('guest message exceeding limit fails', function () {
    $limit = config('secrets.message_length.guest');
    $message = makeEncryptedMessage($limit + 1);

    expect(runMessageLengthValidation('guest', $message))->toBeFalse();
});

test('user with free plan message within limit passes', function () {
    $plan = Plan::factory()->free()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    $limit = $plan->features['messages']['config']['message_length'];
    $message = makeEncryptedMessage($limit - 10);

    expect(runMessageLengthValidation('user', $message))->toBeTrue();
});

test('user with free plan message exceeding limit fails', function () {
    $plan = Plan::factory()->free()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    $limit = $plan->features['messages']['config']['message_length'];
    $message = makeEncryptedMessage($limit + 1);

    expect(runMessageLengthValidation('user', $message))->toBeFalse();
});

test('subscribed user message within plan limit passes', function () {
    $plan = Plan::factory()->withApiAccess()->create();
    $user = User::factory()->withPersonalTeam()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_msg',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    $planLimit = $plan->features['messages']['config']['message_length'];
    $message = makeEncryptedMessage($planLimit - 10);

    expect(runMessageLengthValidation('user', $message))->toBeTrue();
});

test('subscribed user message exceeding plan limit fails', function () {
    $plan = Plan::factory()->withApiAccess()->create();
    $user = User::factory()->withPersonalTeam()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_msg2',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);

    $planLimit = $plan->features['messages']['config']['message_length'];
    $message = makeEncryptedMessage($planLimit + 1);

    expect(runMessageLengthValidation('user', $message))->toBeFalse();
});

test('message below minimum length fails', function () {
    $message = makeEncryptedMessage(0);

    expect(runMessageLengthValidation('guest', $message, 1))->toBeFalse();
});

test('message at exact boundary passes', function () {
    $limit = config('secrets.message_length.guest');
    $message = makeEncryptedMessage($limit);

    expect(runMessageLengthValidation('guest', $message))->toBeTrue();
});

test('message one over boundary fails', function () {
    $limit = config('secrets.message_length.guest');
    $message = makeEncryptedMessage($limit + 1);

    expect(runMessageLengthValidation('guest', $message))->toBeFalse();
});
