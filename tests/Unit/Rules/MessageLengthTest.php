<?php

namespace Tests\Unit\Rules;

use App\Models\Plan;
use App\Models\User;
use App\Rules\MessageLength;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageLengthTest extends TestCase
{
    use RefreshDatabase;

    private function makeEncryptedMessage(int $plaintextLength): string
    {
        $salt = str_repeat('a', 16);
        $header = str_repeat("\0", 28);
        $plaintext = str_repeat('x', $plaintextLength);

        return $salt.base64_encode($header.$plaintext);
    }

    private function validate(string $userType, string $message, int $minLength = 1): bool
    {
        $rule = new MessageLength($userType, $minLength);
        $passed = true;

        $rule->validate('message', $message, function () use (&$passed) {
            $passed = false;
        });

        return $passed;
    }

    public function test_guest_message_within_limit_passes(): void
    {
        $limit = config('secrets.message_length.guest');
        $message = $this->makeEncryptedMessage($limit - 10);

        $this->assertTrue($this->validate('guest', $message));
    }

    public function test_guest_message_exceeding_limit_fails(): void
    {
        $limit = config('secrets.message_length.guest');
        $message = $this->makeEncryptedMessage($limit + 1);

        $this->assertFalse($this->validate('guest', $message));
    }

    public function test_user_with_free_plan_message_within_limit_passes(): void
    {
        $plan = Plan::factory()->free()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        request()->setUserResolver(fn () => $user);

        $limit = $plan->features['messages']['config']['message_length'];
        $message = $this->makeEncryptedMessage($limit - 10);

        $this->assertTrue($this->validate('user', $message));
    }

    public function test_user_with_free_plan_message_exceeding_limit_fails(): void
    {
        $plan = Plan::factory()->free()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        request()->setUserResolver(fn () => $user);

        $limit = $plan->features['messages']['config']['message_length'];
        $message = $this->makeEncryptedMessage($limit + 1);

        $this->assertFalse($this->validate('user', $message));
    }

    public function test_subscribed_user_message_within_plan_limit_passes(): void
    {
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
        $message = $this->makeEncryptedMessage($planLimit - 10);

        $this->assertTrue($this->validate('user', $message));
    }

    public function test_subscribed_user_message_exceeding_plan_limit_fails(): void
    {
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
        $message = $this->makeEncryptedMessage($planLimit + 1);

        $this->assertFalse($this->validate('user', $message));
    }

    public function test_message_below_minimum_length_fails(): void
    {
        $message = $this->makeEncryptedMessage(0);

        $this->assertFalse($this->validate('guest', $message, 1));
    }

    public function test_message_at_exact_boundary_passes(): void
    {
        $limit = config('secrets.message_length.guest');
        $message = $this->makeEncryptedMessage($limit);

        $this->assertTrue($this->validate('guest', $message));
    }

    public function test_message_one_over_boundary_fails(): void
    {
        $limit = config('secrets.message_length.guest');
        $message = $this->makeEncryptedMessage($limit + 1);

        $this->assertFalse($this->validate('guest', $message));
    }
}
