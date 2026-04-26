<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecretThrottlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function payload(): array
    {
        return ['message' => 'test secret', 'expires_in' => 5];
    }

    private function subscribe(User $user, Plan $plan): void
    {
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->word(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
    }

    private function planWithThrottle(int $perMinute): Plan
    {
        return Plan::factory()->create([
            'features' => [
                'messages' => ['order' => 1, 'type' => 'limit', 'config' => ['message_length' => 10000]],
                'expiry' => ['order' => 2, 'type' => 'limit', 'config' => ['expiry_minutes' => 1440]],
                'throttling' => ['order' => 3, 'type' => 'limit', 'config' => ['per_minute' => $perMinute]],
            ],
        ]);
    }

    private function planWithoutThrottle(): Plan
    {
        return Plan::factory()->create([
            'features' => [
                'messages' => ['order' => 1, 'type' => 'limit', 'config' => ['message_length' => 10000]],
                'expiry' => ['order' => 2, 'type' => 'limit', 'config' => ['expiry_minutes' => 1440]],
            ],
        ]);
    }

    public function test_subscribed_user_with_throttling_feature_is_rate_limited(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->subscribe($user, $this->planWithThrottle(perMinute: 2));

        $this->actingAs($user);

        $this->post(route('secret.store'), $this->payload())->assertRedirect();
        $this->post(route('secret.store'), $this->payload())->assertRedirect();
        $this->post(route('secret.store'), $this->payload())->assertStatus(429);
    }

    public function test_throttle_limit_respects_per_minute_value_from_plan(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->subscribe($user, $this->planWithThrottle(perMinute: 4));

        $this->actingAs($user);

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('secret.store'), $this->payload())->assertRedirect();
        }

        $this->post(route('secret.store'), $this->payload())->assertStatus(429);
    }

    public function test_subscribed_user_without_throttling_feature_is_not_rate_limited(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->subscribe($user, $this->planWithoutThrottle());

        $this->actingAs($user);

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('secret.store'), $this->payload())->assertRedirect();
        }
    }

    public function test_unsubscribed_user_uses_config_rate_limit(): void
    {
        $limit = config('secrets.rate_limit.user.per_minute');
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        for ($i = 0; $i < $limit; $i++) {
            $this->post(route('secret.store'), $this->payload())->assertRedirect();
        }

        $this->post(route('secret.store'), $this->payload())->assertStatus(429);
    }
}
