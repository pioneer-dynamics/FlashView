<?php

namespace Tests\Feature\Api;

use App\Exceptions\InvalidHashIdException;
use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class SecretRetrievalTest extends TestCase
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
                'notification' => [
                    'order' => 4.5,
                    'label' => 'Get notified when a message is retrieved',
                    'config' => ['email' => true],
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

    /**
     * Allow the `retrieved` Eloquent event to fire during HTTP tests.
     *
     * The event skips when `App::runningInConsole()` is true, which is
     * always true during PHPUnit. This override simulates real HTTP
     * behaviour so the event marks secrets as retrieved.
     */
    private function simulateHttpMode(): void
    {
        (new ReflectionProperty(app(), 'isRunningInConsole'))->setValue(app(), false);
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

    public function test_can_retrieve_secret_message(): void
    {
        $this->simulateHttpMode();
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['hash_id', 'message'],
            ])
            ->assertJson([
                'data' => [
                    'hash_id' => $secret->hash_id,
                ],
            ]);

        $this->assertNotNull($response->json('data.message'));
    }

    public function test_secret_is_marked_as_retrieved_after_access(): void
    {
        $this->simulateHttpMode();
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
        $this->assertNull($freshSecret->message);
        $this->assertNotNull($freshSecret->retrieved_at);
    }

    public function test_subsequent_retrieval_returns_404(): void
    {
        $this->simulateHttpMode();
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve")
            ->assertOk();

        $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve")
            ->assertNotFound();
    }

    public function test_expired_secret_returns_404(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user, [
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertNotFound();
    }

    public function test_nonexistent_hash_id_throws_model_not_found(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);
        $hashId = $secret->hash_id;
        $secret->forceDelete();

        $this->withoutExceptionHandling();

        $this->expectException(ModelNotFoundException::class);

        $this->getJson("/api/v1/secrets/{$hashId}/retrieve");
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertUnauthorized();
    }

    public function test_user_without_api_access_returns_403(): void
    {
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertForbidden();
    }

    public function test_any_authenticated_user_can_retrieve_others_secret(): void
    {
        $this->simulateHttpMode();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $this->subscribeUserToPlan($otherUser, $this->primePlan);
        Sanctum::actingAs($otherUser, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['hash_id', 'message'],
            ]);
    }

    public function test_already_retrieved_secret_returns_404(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $secret = $this->createSecretForUser($this->user, [
            'retrieved_at' => now()->subHour(),
            'message' => null,
        ]);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertNotFound();
    }

    public function test_token_without_list_ability_returns_403(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $secret = $this->createSecretForUser($this->user);

        $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHashIdProvider(): array
    {
        return [
            'garbage string' => ['invalid-hash'],
            'special characters' => ['!!!@@@'],
            'random alphanumeric' => ['abc123xyz'],
        ];
    }

    #[DataProvider('invalidHashIdProvider')]
    public function test_invalid_hash_id_returns_404(string $invalidHashId): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $response = $this->getJson("/api/v1/secrets/{$invalidHashId}/retrieve");

        $response->assertNotFound();
    }

    #[DataProvider('invalidHashIdProvider')]
    public function test_invalid_hash_id_throws_invalid_hash_id_exception(string $invalidHashId): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:list']);

        $this->withoutExceptionHandling();

        $this->expectException(InvalidHashIdException::class);

        $this->getJson("/api/v1/secrets/{$invalidHashId}/retrieve");
    }

    public function test_forbidden_request_does_not_consume_secret(): void
    {
        $this->subscribeUserToPlan($this->user, $this->primePlan);
        Sanctum::actingAs($this->user, ['secrets:create']);

        $secret = $this->createSecretForUser($this->user);

        $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve")
            ->assertForbidden();

        $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
        $this->assertNotNull($freshSecret->message);
        $this->assertNull($freshSecret->retrieved_at);
    }
}
