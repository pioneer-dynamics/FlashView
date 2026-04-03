<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecretApiTest extends TestCase
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

    /**
     * Build a fake encrypted message that passes MessageLength validation.
     * Format: 16-char salt + base64(28-byte header + plaintext)
     */
    private function buildEncryptedMessage(string $plaintext = 'hello'): string
    {
        $salt = str_repeat('a', 16);
        $header = str_repeat("\0", 28);
        $body = $header.$plaintext;

        return $salt.base64_encode($body);
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

    // --- Store endpoint tests ---

    public function test_can_create_secret_via_api(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $response = $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage('test secret message'),
            'expires_in' => 1440,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['hash_id', 'url', 'expires_at', 'created_at'],
            ]);

        $this->assertDatabaseHas('secrets', [
            'user_id' => $this->user->id,
        ]);

        $this->assertStringContainsString('signature=', $response->json('data.url'));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $response = $this->postJson('/api/v1/secrets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message', 'expires_in']);
    }

    public function test_store_validates_invalid_expiry(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $response = $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage('test'),
            'expires_in' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_in']);
    }

    // --- Index endpoint tests ---

    public function test_can_list_own_secrets(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $this->createSecretForUser($this->user);
        $this->createSecretForUser($this->user);

        $response = $this->getJson('/api/v1/secrets');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['hash_id', 'expires_at', 'created_at', 'is_expired', 'is_retrieved'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_cannot_see_other_users_secrets(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $otherUser = User::factory()->withPersonalTeam()->create();
        $this->createSecretForUser($otherUser);
        $this->createSecretForUser($this->user);

        $response = $this->getJson('/api/v1/secrets');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_includes_expired_and_retrieved_secrets(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $this->createSecretForUser($this->user);
        $this->createSecretForUser($this->user, [
            'expires_at' => now()->subDay(),
            'message' => null,
            'retrieved_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/v1/secrets');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_does_not_burn_secrets(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $this->getJson('/api/v1/secrets');

        $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
        $this->assertNotNull($freshSecret->message);
        $this->assertNull($freshSecret->retrieved_at);
    }

    // --- Destroy endpoint tests ---

    public function test_can_burn_own_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:delete']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertOk()
            ->assertJson(['message' => 'Secret burned successfully.']);

        $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
        $this->assertNull($freshSecret->message);
        $this->assertNotNull($freshSecret->retrieved_at);
    }

    public function test_cannot_burn_other_users_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:delete']);

        $otherUser = User::factory()->withPersonalTeam()->create();
        $secret = $this->createSecretForUser($otherUser);

        $response = $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertForbidden();
    }

    public function test_destroy_does_not_trigger_retrieval_event(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:delete']);

        $secret = $this->createSecretForUser($this->user);

        $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

        $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
        $this->assertNotNull($freshSecret->retrieved_at);
    }

    // --- Token abilities tests ---

    public function test_store_requires_create_ability(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $response = $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage('test'),
            'expires_in' => 1440,
        ]);

        $response->assertForbidden();
    }

    public function test_index_requires_list_ability(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $response = $this->getJson('/api/v1/secrets');

        $response->assertForbidden();
    }

    public function test_destroy_requires_delete_ability(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

        $response->assertForbidden();
    }

    // --- Authentication tests ---

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/secrets');

        $response->assertUnauthorized();
    }

    // --- Plan-based access gating tests ---

    public function test_user_without_subscription_gets_403(): void
    {
        Sanctum::actingAs($this->user, ['secrets:list']);

        $response = $this->getJson('/api/v1/secrets');

        $response->assertForbidden()
            ->assertJson(['message' => 'API access requires an active subscription with API support.']);
    }

    public function test_user_with_non_api_plan_gets_403(): void
    {
        $basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic',
            'stripe_yearly_price_id' => 'price_yearly_basic',
            'stripe_product_id' => 'prod_basic',
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => [
                'api' => [
                    'order' => 6,
                    'label' => 'API Access',
                    'config' => [],
                    'type' => 'missing',
                ],
            ],
        ]);

        $this->subscribeUserToPlan($this->user, $basicPlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $response = $this->getJson('/api/v1/secrets');

        $response->assertForbidden()
            ->assertJson(['message' => 'Your current plan does not include API access. Please upgrade to a plan with API support.']);
    }

    // --- Token revocation tests ---

    public function test_tokens_revoked_when_subscription_cancelled(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->createToken('test-token', ['secrets:create']);
        $this->assertCount(1, $this->user->fresh()->tokens);

        $subscription = $this->user->subscriptions()->first();
        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        $this->assertCount(0, $this->user->fresh()->tokens);
    }

    public function test_tokens_revoked_when_plan_downgraded(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->createToken('test-token', ['secrets:create']);
        $this->assertCount(1, $this->user->fresh()->tokens);

        $basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic_2',
            'stripe_yearly_price_id' => 'price_yearly_basic_2',
            'stripe_product_id' => 'prod_basic_2',
            'price_per_month' => 25,
            'price_per_year' => 250,
            'features' => [
                'api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'missing'],
            ],
        ]);

        $subscription = $this->user->subscriptions()->first();
        $subscription->update([
            'stripe_price' => $basicPlan->stripe_monthly_price_id,
        ]);

        $this->assertCount(0, $this->user->fresh()->tokens);
    }

    public function test_tokens_kept_when_plan_still_has_api_access(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->user->createToken('test-token', ['secrets:create']);
        $this->assertCount(1, $this->user->fresh()->tokens);

        $subscription = $this->user->subscriptions()->first();
        $subscription->update([
            'stripe_price' => $this->primePlan->stripe_yearly_price_id,
        ]);

        $this->assertCount(1, $this->user->fresh()->tokens);
    }

    // --- Token management page gating tests ---

    public function test_token_management_page_accessible_with_api_plan(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        $this->actingAs($this->user);

        $response = $this->get('/user/api-tokens');

        $response->assertOk();
    }

    public function test_token_management_page_blocked_without_api_plan(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/user/api-tokens');

        $response->assertForbidden();
    }
}
