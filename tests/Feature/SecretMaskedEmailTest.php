<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecretMaskedEmailTest extends TestCase
{
    use RefreshDatabase;

    private function createApiPlan(string $monthlyPriceId, string $yearlyPriceId, string $productId): Plan
    {
        return Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => $monthlyPriceId,
            'stripe_yearly_price_id' => $yearlyPriceId,
            'stripe_product_id' => $productId,
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => [
                'messages' => ['order' => 2, 'label' => 'Messages', 'config' => ['message_length' => 100000], 'type' => 'feature'],
                'expiry' => ['order' => 3, 'label' => 'Expiry', 'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200], 'type' => 'feature'],
                'api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature'],
            ],
        ]);
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

    private function validSecretPayload(int $plaintextLength = 50, int $expiresIn = 5): array
    {
        return [
            'message' => (new SecretFactory)->generateEncryptedMessage($plaintextLength),
            'expires_in' => $expiresIn,
        ];
    }

    public function test_masked_email_stored_when_setting_enabled(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('secret.store'), array_merge($this->validSecretPayload(), [
                'email' => 'recipient@example.com',
            ]));

        $response->assertSessionHasNoErrors();

        $secret = $user->secrets()->first();
        $this->assertNotNull($secret->masked_recipient_email);
        $this->assertStringStartsWith('r', $secret->masked_recipient_email);
        $this->assertStringNotContainsString('ecipient', $secret->masked_recipient_email);
        $this->assertStringNotContainsString('recipient@example.com', $secret->masked_recipient_email);
    }

    public function test_masked_email_not_stored_when_setting_disabled(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => false,
        ]);

        $response = $this->actingAs($user)
            ->post(route('secret.store'), array_merge($this->validSecretPayload(), [
                'email' => 'recipient@example.com',
            ]));

        $response->assertSessionHasNoErrors();

        $secret = $user->secrets()->first();
        $this->assertNull($secret->masked_recipient_email);
    }

    public function test_no_masked_email_stored_when_no_email_provided(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload());

        $response->assertSessionHasNoErrors();

        $secret = $user->secrets()->first();
        $this->assertNull($secret->masked_recipient_email);
    }

    public function test_masked_email_appears_in_secrets_list(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $this->actingAs($user)
            ->post(route('secret.store'), array_merge($this->validSecretPayload(), [
                'email' => 'recipient@example.com',
            ]));

        $response = $this->actingAs($user)->get(route('secrets.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Secret/Index')
            ->has('secrets.data.0.masked_recipient_email')
        );
    }

    public function test_masked_email_null_in_secrets_list_when_no_email_provided(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $this->actingAs($user)->post(route('secret.store'), $this->validSecretPayload());

        $response = $this->actingAs($user)->get(route('secrets.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Secret/Index')
            ->where('secrets.data.0.masked_recipient_email', null)
        );
    }

    public function test_api_stores_masked_email_when_setting_enabled(): void
    {
        $primePlan = $this->createApiPlan('price_monthly_prime_mask', 'price_yearly_prime_mask', 'prod_prime_mask');

        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => true,
        ]);

        $this->subscribeUserToPlan($user, $primePlan);

        Sanctum::actingAs($user, ['secrets:create']);

        $response = $this->postJson('/api/v1/secrets', [
            'message' => (new SecretFactory)->generateEncryptedMessage(50),
            'expires_in' => 1440,
            'email' => 'recipient@example.com',
        ]);

        $response->assertStatus(201);

        $secret = $user->secrets()->first();
        $this->assertNotNull($secret->masked_recipient_email);
        $this->assertStringNotContainsString('recipient@example.com', $secret->masked_recipient_email);
    }

    public function test_api_does_not_store_masked_email_when_setting_disabled(): void
    {
        $primePlan = $this->createApiPlan('price_monthly_prime_mask2', 'price_yearly_prime_mask2', 'prod_prime_mask2');

        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => false,
        ]);

        $this->subscribeUserToPlan($user, $primePlan);

        Sanctum::actingAs($user, ['secrets:create']);

        $response = $this->postJson('/api/v1/secrets', [
            'message' => (new SecretFactory)->generateEncryptedMessage(50),
            'expires_in' => 1440,
            'email' => 'recipient@example.com',
        ]);

        $response->assertStatus(201);

        $secret = $user->secrets()->first();
        $this->assertNull($secret->masked_recipient_email);
    }

    public function test_setting_enabled_after_creation_does_not_mask_old_secrets(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'store_masked_recipient_email' => false,
        ]);

        // Create without masking
        $this->actingAs($user)->post(route('secret.store'), array_merge($this->validSecretPayload(), [
            'email' => 'recipient@example.com',
        ]));

        $secretBefore = $user->secrets()->first();
        $this->assertNull($secretBefore->masked_recipient_email);

        // Enable the setting — existing secret should remain unaffected
        $user->update(['store_masked_recipient_email' => true]);

        $secretAfter = $user->secrets()->first();
        $this->assertNull($secretAfter->masked_recipient_email);
    }
}
