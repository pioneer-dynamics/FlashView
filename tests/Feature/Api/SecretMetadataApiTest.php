<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecretMetadataApiTest extends TestCase
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
                'email_notification' => [
                    'order' => 4.5,
                    'label' => 'Email Notifications',
                    'config' => ['email' => true],
                    'type' => 'feature',
                ],
                'webhook_notification' => [
                    'order' => 4.6,
                    'label' => 'Webhook Notifications',
                    'config' => ['webhook' => false],
                    'type' => 'missing',
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

    private function createSecretForUser(User $user, array $overrides = []): Secret
    {
        return Secret::withoutEvents(fn () => Secret::forceCreate(array_merge([
            'message' => encrypt('test-encrypted-message'),
            'expires_at' => now()->addDay(),
            'user_id' => $user->id,
            'ip_address_sent' => encrypt('127.0.0.1', false),
        ], $overrides)));
    }

    public function test_can_show_own_secret_metadata(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['hash_id', 'expires_at', 'created_at', 'is_expired', 'is_retrieved', 'retrieved_at'],
            ])
            ->assertJson([
                'data' => [
                    'hash_id' => $secret->hash_id,
                    'is_expired' => false,
                    'is_retrieved' => false,
                ],
            ]);
    }

    public function test_cannot_show_other_users_secret_metadata(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $otherUser = User::factory()->withPersonalTeam()->create();
        $secret = $this->createSecretForUser($otherUser);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertForbidden();
    }

    public function test_show_returns_403_for_nonexistent_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $response = $this->getJson('/api/v1/secrets/invalidhashid');

        $response->assertForbidden();
    }

    public function test_show_requires_list_ability(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertForbidden();
    }

    public function test_show_does_not_mark_secret_as_retrieved(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $this->getJson("/api/v1/secrets/{$secret->hash_id}");

        $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
        $this->assertNotNull($freshSecret->message);
        $this->assertNull($freshSecret->retrieved_at);
    }

    public function test_show_works_for_expired_secrets(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user, [
            'expires_at' => now()->subDay(),
            'message' => null,
        ]);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'hash_id' => $secret->hash_id,
                    'is_expired' => true,
                ],
            ]);
    }

    public function test_show_works_for_retrieved_secrets(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user, [
            'retrieved_at' => now()->subHour(),
            'message' => null,
        ]);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'hash_id' => $secret->hash_id,
                    'is_retrieved' => true,
                ],
            ]);
    }
}
