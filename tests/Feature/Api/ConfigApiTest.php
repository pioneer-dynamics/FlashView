<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => [
                'messages' => [
                    'order' => 2,
                    'label' => ':message_length character limit per message',
                    'config' => ['message_length' => 100000],
                    'type' => 'feature',
                ],
                'expiry' => [
                    'order' => 3,
                    'label' => 'Maximum expiry of :expiry_label',
                    'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200],
                    'type' => 'feature',
                ],
                'api' => [
                    'order' => 6,
                    'label' => 'API Access',
                    'config' => [],
                    'type' => 'feature',
                ],
            ],
        ]);

        $this->user = User::factory()->withPersonalTeam()->create();
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

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/config');

        $response->assertUnauthorized();
    }

    public function test_user_without_subscription_gets_403(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertForbidden();
    }

    public function test_config_endpoint_returns_expiry_options(): void
    {
        $this->subscribeUserToPlan($this->user, $this->plan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonStructure([
                'expiry_options' => [
                    ['label', 'value'],
                ],
                'expiry_limits' => ['guest', 'user'],
                'message_length' => ['guest', 'user'],
            ]);
    }

    public function test_config_values_match_server_config(): void
    {
        $this->subscribeUserToPlan($this->user, $this->plan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonFragment([
                'expiry_options' => config('secrets.expiry_options'),
                'expiry_limits' => config('secrets.expiry_limits'),
                'message_length' => config('secrets.message_length'),
            ]);
    }

    public function test_config_endpoint_includes_plan_limits_for_subscribed_users(): void
    {
        $this->subscribeUserToPlan($this->user, $this->plan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonPath('plan_limits.expiry_minutes', 43200)
            ->assertJsonPath('plan_limits.message_length', 100000);
    }

    public function test_config_endpoint_excludes_plan_limits_when_plan_record_missing(): void
    {
        $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_orphan',
            'stripe_status' => 'active',
            'stripe_price' => 'price_nonexistent',
            'quantity' => 1,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertForbidden();
    }
}
