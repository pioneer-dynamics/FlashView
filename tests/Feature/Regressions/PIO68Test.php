<?php

namespace Tests\Feature\Regressions;

use App\Models\Plan;
use App\Models\SenderIdentity;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @see PIO-68
 *
 * CLI-created secrets do not mark sender as verified.
 * A user with an active verified sender badge creates a secret via the API;
 * the secret should carry the sender identity snapshot, but currently does not.
 */
class PIO68Test extends TestCase
{
    use RefreshDatabase;

    private function createPrimeUser(): User
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->word(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function createBasicUser(): User
    {
        $plan = Plan::factory()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_'.fake()->unique()->word(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function buildEncryptedMessage(): string
    {
        return (new SecretFactory)->generateEncryptedMessage(50);
    }

    // -----------------------------------------------------------------------
    // Regression test — must fail before fix, pass after
    // -----------------------------------------------------------------------

    /**
     * Core regression: a user with an active verified domain sender identity
     * creates a secret via the CLI (API); the secret must carry the sender
     * identity snapshot (company_name + domain).
     */
    public function test_cli_secret_carries_verified_sender_identity_when_user_has_active_badge(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'email' => null,
            'verification_token' => 'some-token',
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($user, ['secrets:create']);

        $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage(),
            'expires_in' => 1440,
            'include_sender_identity' => true,
        ])->assertStatus(201);

        $secret = $user->secrets()->first();
        $this->assertEquals('Acme Corp', $secret->sender_company_name);
        $this->assertEquals('acme.com', $secret->sender_domain);
        $this->assertNull($secret->sender_email);
    }

    // -----------------------------------------------------------------------
    // Happy paths
    // -----------------------------------------------------------------------

    public function test_cli_email_identity_snapshot_attached_to_secret(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'company_name' => null,
            'domain' => null,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($user, ['secrets:create']);

        $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage(),
            'expires_in' => 1440,
            'include_sender_identity' => true,
        ])->assertStatus(201);

        $secret = $user->secrets()->first();
        $this->assertNull($secret->sender_company_name);
        $this->assertNull($secret->sender_domain);
        $this->assertEquals($user->email, $secret->sender_email);
    }

    // -----------------------------------------------------------------------
    // Edge paths
    // -----------------------------------------------------------------------

    public function test_cli_unverified_identity_not_applied(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'email' => null,
            'verification_token' => 'some-token',
            'verified_at' => null,
        ]);

        Sanctum::actingAs($user, ['secrets:create']);

        $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage(),
            'expires_in' => 1440,
        ])->assertStatus(201);

        $secret = $user->secrets()->first();
        $this->assertNull($secret->sender_company_name);
        $this->assertNull($secret->sender_domain);
        $this->assertNull($secret->sender_email);
    }

    public function test_cli_user_without_sender_identity_plan_gets_no_snapshot(): void
    {
        $user = $this->createBasicUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($user, ['secrets:create']);

        $this->postJson('/api/v1/secrets', [
            'message' => $this->buildEncryptedMessage(),
            'expires_in' => 1440,
        ])->assertStatus(201);

        $secret = $user->secrets()->first();
        $this->assertNull($secret->sender_company_name);
        $this->assertNull($secret->sender_domain);
        $this->assertNull($secret->sender_email);
    }
}
