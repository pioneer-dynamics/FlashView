<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SenderIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StegoRouteTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Index route
    // -----------------------------------------------------------------------

    public function test_stego_page_is_accessible_to_guests(): void
    {
        $response = $this->get(route('stego.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Secret/StegoPage'));
    }

    public function test_stego_page_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('stego.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Secret/StegoPage'));
    }

    // -----------------------------------------------------------------------
    // POST /stego/sign
    // -----------------------------------------------------------------------

    public function test_sign_requires_authentication(): void
    {
        $response = $this->postJson(route('stego.sign'), ['ciphertext' => 'abc']);

        $response->assertStatus(401);
    }

    public function test_sign_returns_403_without_verified_identity(): void
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_sign_no_identity',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
        // No SenderIdentity created

        $response = $this->actingAs($user)->postJson(route('stego.sign'), ['ciphertext' => 'abc']);

        $response->assertStatus(403);
    }

    public function test_sign_returns_403_when_plan_does_not_support_sender_identity(): void
    {
        // Basic plan (no sender identity feature)
        $plan = Plan::factory()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_sign_basic_plan',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
        SenderIdentity::factory()->for($user)->create(['verified_at' => now()]);

        $response = $this->actingAs($user)->postJson(route('stego.sign'), ['ciphertext' => 'abc']);

        $response->assertStatus(403);
    }

    public function test_sign_returns_signature_and_identity_for_verified_user(): void
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_sign_ok',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);
        $identity = SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => 'sender@example.com',
            'verified_at' => now(),
        ]);

        $ciphertext = 'encrypted-payload-abc123';
        $response = $this->actingAs($user)->postJson(route('stego.sign'), ['ciphertext' => $ciphertext]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['signature', 'verified_identity']);
        $response->assertJsonPath('verified_identity.email', 'sender@example.com');
        $response->assertJsonPath('verified_identity.type', 'email');

        // Verify the returned signature is correct
        $verifiedIdentity = [
            'company_name' => $identity->company_name,
            'domain' => $identity->domain,
            'email' => $identity->email,
            'type' => $identity->type,
        ];
        ksort($verifiedIdentity);
        $payload = ['ciphertext' => $ciphertext, 'verified_identity' => $verifiedIdentity];
        ksort($payload);
        $expectedSignature = hash_hmac('sha256', json_encode($payload), config('app.key'));

        $response->assertJsonPath('signature', $expectedSignature);
    }

    // -----------------------------------------------------------------------
    // POST /stego/verify
    // -----------------------------------------------------------------------

    public function test_verify_does_not_require_authentication(): void
    {
        $ciphertext = 'test-cipher';
        $verifiedIdentity = [
            'company_name' => null,
            'domain' => null,
            'email' => 'test@example.com',
            'type' => 'email',
        ];
        ksort($verifiedIdentity);
        $payload = ['ciphertext' => $ciphertext, 'verified_identity' => $verifiedIdentity];
        ksort($payload);
        $signature = hash_hmac('sha256', json_encode($payload), config('app.key'));

        $response = $this->postJson(route('stego.verify'), [
            'ciphertext' => $ciphertext,
            'verified_identity' => $verifiedIdentity,
            'signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('verified', true);
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $ciphertext = 'my-secret-ciphertext';
        $verifiedIdentity = [
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'email' => null,
            'type' => 'domain',
        ];
        ksort($verifiedIdentity);
        $payload = ['ciphertext' => $ciphertext, 'verified_identity' => $verifiedIdentity];
        ksort($payload);
        $signature = hash_hmac('sha256', json_encode($payload), config('app.key'));

        $response = $this->postJson(route('stego.verify'), [
            'ciphertext' => $ciphertext,
            'verified_identity' => $verifiedIdentity,
            'signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('verified', true);
    }

    public function test_verify_returns_false_for_tampered_ciphertext(): void
    {
        $originalCiphertext = 'original-ciphertext';
        $verifiedIdentity = [
            'company_name' => null,
            'domain' => null,
            'email' => 'sender@example.com',
            'type' => 'email',
        ];
        ksort($verifiedIdentity);
        $payload = ['ciphertext' => $originalCiphertext, 'verified_identity' => $verifiedIdentity];
        ksort($payload);
        $signature = hash_hmac('sha256', json_encode($payload), config('app.key'));

        $response = $this->postJson(route('stego.verify'), [
            'ciphertext' => 'tampered-ciphertext',
            'verified_identity' => $verifiedIdentity,
            'signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('verified', false);
    }

    public function test_verify_returns_false_for_tampered_identity(): void
    {
        $ciphertext = 'my-ciphertext';
        $verifiedIdentity = [
            'company_name' => null,
            'domain' => null,
            'email' => 'real@example.com',
            'type' => 'email',
        ];
        ksort($verifiedIdentity);
        $payload = ['ciphertext' => $ciphertext, 'verified_identity' => $verifiedIdentity];
        ksort($payload);
        $signature = hash_hmac('sha256', json_encode($payload), config('app.key'));

        $tamperedIdentity = [
            'company_name' => null,
            'domain' => null,
            'email' => 'attacker@evil.com',
            'type' => 'email',
        ];

        $response = $this->postJson(route('stego.verify'), [
            'ciphertext' => $ciphertext,
            'verified_identity' => $tamperedIdentity,
            'signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('verified', false);
    }

    public function test_verify_returns_false_for_invalid_signature(): void
    {
        $response = $this->postJson(route('stego.verify'), [
            'ciphertext' => 'any-ciphertext',
            'verified_identity' => [
                'type' => 'email',
                'company_name' => null,
                'domain' => null,
                'email' => 'test@example.com',
            ],
            'signature' => 'invalid-signature-string',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('verified', false);
    }
}
