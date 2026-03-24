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

    public function test_config_returns_user_specific_limits(): void
    {
        $this->subscribeUserToPlan($this->user, $this->plan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonStructure([
                'expiry_options' => [
                    ['label', 'value'],
                ],
                'max_expiry',
                'max_message_length',
            ])
            ->assertJsonPath('max_expiry', 43200)
            ->assertJsonPath('max_message_length', 100000);
    }

    public function test_config_does_not_expose_raw_limits(): void
    {
        $this->subscribeUserToPlan($this->user, $this->plan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonMissing(['expiry_limits'])
            ->assertJsonMissing(['message_length' => config('secrets.message_length')])
            ->assertJsonMissing(['plan_limits']);
    }

    public function test_expiry_options_filtered_to_user_max(): void
    {
        $limitedPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic',
            'stripe_yearly_price_id' => 'price_yearly_basic',
            'stripe_product_id' => 'prod_basic',
            'price_per_month' => 10,
            'price_per_year' => 100,
            'features' => [
                'messages' => [
                    'order' => 2,
                    'label' => ':message_length character limit per message',
                    'config' => ['message_length' => 500],
                    'type' => 'feature',
                ],
                'expiry' => [
                    'order' => 3,
                    'label' => 'Maximum expiry of :expiry_label',
                    'config' => ['expiry_label' => '1 day', 'expiry_minutes' => 1440],
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

        $this->subscribeUserToPlan($this->user, $limitedPlan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $response->assertOk()
            ->assertJsonPath('max_expiry', 1440)
            ->assertJsonPath('max_message_length', 500);

        $expiryValues = array_column($response->json('expiry_options'), 'value');

        // All returned options should be <= 1440 minutes (1 day)
        foreach ($expiryValues as $value) {
            $this->assertLessThanOrEqual(1440, $value);
        }

        // Options beyond 1 day should not be present
        $this->assertNotContains(4320, $expiryValues);
        $this->assertNotContains(10080, $expiryValues);
    }

    public function test_full_plan_gets_all_expiry_options(): void
    {
        $this->subscribeUserToPlan($this->user, $this->plan);
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/config');

        $expiryValues = array_column($response->json('expiry_options'), 'value');

        $this->assertCount(count(config('secrets.expiry_options')), $expiryValues);
    }
}
