<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $primePlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primePlan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => [
                'notification' => [
                    'order' => 4.5,
                    'label' => 'Notifications',
                    'config' => ['email' => true, 'webhook' => true],
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

    public function test_can_get_webhook_config(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->getJson('/api/v1/webhook');

        $response->assertOk()
            ->assertJsonStructure(['webhook_url', 'webhook_secret', 'configured']);
    }

    public function test_can_update_webhook_url(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->putJson('/api/v1/webhook', [
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $response->assertOk()
            ->assertJson([
                'webhook_url' => 'https://example.com/webhook',
                'configured' => true,
                'message' => 'Webhook settings updated.',
            ]);

        $this->user->refresh();
        $this->assertEquals('https://example.com/webhook', $this->user->webhook_url);
        $this->assertNotNull($this->user->webhook_secret);
    }

    public function test_can_regenerate_webhook_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        $oldSecret = $this->user->webhook_secret;
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->postJson('/api/v1/webhook/regenerate-secret');

        $response->assertOk()
            ->assertJsonStructure(['webhook_secret', 'message']);

        $this->user->refresh();
        $this->assertNotEquals($oldSecret, $this->user->webhook_secret);
    }

    public function test_regenerate_returns_full_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->postJson('/api/v1/webhook/regenerate-secret');

        $newSecret = $response->json('webhook_secret');
        $this->assertEquals(64, strlen($newSecret));
        $this->assertStringNotContainsString('*', $newSecret);
    }

    public function test_cannot_regenerate_without_webhook_configured(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->postJson('/api/v1/webhook/regenerate-secret');

        $response->assertStatus(422);
    }

    public function test_can_delete_webhook(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->deleteJson('/api/v1/webhook');

        $response->assertOk()
            ->assertJson(['message' => 'Webhook configuration removed.']);

        $this->user->refresh();
        $this->assertNull($this->user->webhook_url);
        $this->assertNull($this->user->webhook_secret);
    }

    public function test_rejects_token_without_webhook_manage_ability(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $response = $this->getJson('/api/v1/webhook');

        $response->assertStatus(403);
    }

    public function test_rejects_user_without_api_plan(): void
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'stripe_monthly_price_id' => 'price_monthly_free',
            'stripe_yearly_price_id' => 'price_yearly_free',
            'stripe_product_id' => 'prod_free',
            'price_per_month' => 0,
            'price_per_year' => 0,
            'features' => [
                'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
            ],
        ]);

        $this->subscribeUserToPlan($this->user, $freePlan);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->getJson('/api/v1/webhook');

        $response->assertStatus(403);
    }

    public function test_validation_rejects_non_https_url_via_api(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->putJson('/api/v1/webhook', [
            'webhook_url' => 'http://example.com/webhook',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('webhook_url');
    }

    public function test_show_returns_masked_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->updateQuietly([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
        ]);
        Sanctum::actingAs($this->user, ['webhook:manage']);

        $response = $this->getJson('/api/v1/webhook');

        $maskedSecret = $response->json('webhook_secret');
        $this->assertStringContainsString('*', $maskedSecret);
        $this->assertStringEndsWith('34567890', $maskedSecret);
    }
}
