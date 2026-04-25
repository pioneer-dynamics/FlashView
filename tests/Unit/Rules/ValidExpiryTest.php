<?php

namespace Tests\Unit\Rules;

use App\Models\Plan;
use App\Models\User;
use App\Rules\ValidExpiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function validate(string $userType, mixed $value): bool
    {
        $rule = new ValidExpiry($userType);
        $passed = true;

        $rule->validate('expires_in', $value, function () use (&$passed) {
            $passed = false;
        });

        return $passed;
    }

    public function test_valid_guest_expiry_passes(): void
    {
        $this->assertTrue($this->validate('guest', 5));
    }

    public function test_guest_expiry_exceeding_limit_fails(): void
    {
        $guestLimit = config('secrets.expiry_limits.guest');
        $beyondLimit = collect(config('secrets.expiry_options'))
            ->firstWhere(fn ($opt) => $opt['value'] > $guestLimit);

        if ($beyondLimit) {
            $this->assertFalse($this->validate('guest', $beyondLimit['value']));
        } else {
            $this->markTestSkipped('No expiry option exceeds guest limit.');
        }
    }

    public function test_valid_user_expiry_passes(): void
    {
        $plan = Plan::factory()->free()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        request()->setUserResolver(fn () => $user);

        $this->assertTrue($this->validate('user', 5));
    }

    public function test_user_expiry_exceeding_free_plan_limit_fails(): void
    {
        $plan = Plan::factory()->free()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        request()->setUserResolver(fn () => $user);

        $planExpiryMinutes = $plan->features['expiry']['config']['expiry_minutes'];
        $beyondLimit = collect(config('secrets.expiry_options'))
            ->firstWhere(fn ($opt) => $opt['value'] > $planExpiryMinutes);

        if ($beyondLimit) {
            $this->assertFalse($this->validate('user', $beyondLimit['value']));
        } else {
            $this->markTestSkipped('No expiry option exceeds free plan limit.');
        }
    }

    public function test_invalid_expiry_value_fails(): void
    {
        $this->assertFalse($this->validate('guest', 999));
    }

    public function test_all_guest_allowed_options_pass(): void
    {
        $guestLimit = config('secrets.expiry_limits.guest');
        $allowed = collect(config('secrets.expiry_options'))
            ->filter(fn ($opt) => $opt['value'] <= $guestLimit);

        foreach ($allowed as $option) {
            $this->assertTrue(
                $this->validate('guest', $option['value']),
                "Expected expiry value {$option['value']} to pass for guest"
            );
        }
    }

    public function test_subscribed_user_expiry_uses_plan_limit(): void
    {
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
            $this->assertTrue(
                $this->validate('user', $option['value']),
                "Expected expiry value {$option['value']} to pass for subscribed user"
            );
        }
    }
}
