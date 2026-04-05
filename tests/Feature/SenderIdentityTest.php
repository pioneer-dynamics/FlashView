<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Secret;
use App\Models\SenderIdentity;
use App\Models\User;
use App\Services\DomainVerificationService;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SenderIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function createPrimeUser(): User
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_sender_identity',
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
            'stripe_id' => 'sub_test_basic',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function validSecretPayload(int $expiresIn = 5): array
    {
        return [
            'message' => (new SecretFactory)->generateEncryptedMessage(50),
            'expires_in' => $expiresIn,
        ];
    }

    // -----------------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------------

    public function test_guest_cannot_store_sender_identity(): void
    {
        $this->post(route('user.sender-identity.store'), ['type' => 'email'])
            ->assertRedirect(route('login'));
    }

    public function test_unsubscribed_user_gets_403_on_sender_identity_store(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), ['type' => 'email'])
            ->assertForbidden();
    }

    public function test_basic_plan_user_gets_403_on_sender_identity_store(): void
    {
        $user = $this->createBasicUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), ['type' => 'email'])
            ->assertForbidden();
    }

    public function test_unsubscribed_user_gets_403_on_verify(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->post(route('user.sender-identity.verify'))
            ->assertForbidden();
    }

    public function test_unsubscribed_user_gets_403_on_destroy(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->delete(route('user.sender-identity.destroy'))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Store — email identity
    // -----------------------------------------------------------------------

    public function test_prime_user_can_create_email_identity(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), ['type' => 'email'])
            ->assertRedirect();

        $identity = $user->fresh()->senderIdentity;
        $this->assertNotNull($identity);
        $this->assertEquals('email', $identity->type);
        $this->assertEquals($user->email, $identity->email);
        $this->assertNotNull($identity->verified_at);
        $this->assertNull($identity->domain);
        $this->assertNull($identity->company_name);
        $this->assertNull($identity->verification_token);
    }

    public function test_email_identity_is_immediately_verified(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), ['type' => 'email']);

        $this->assertTrue($user->fresh()->hasVerifiedSenderIdentity());
    }

    // -----------------------------------------------------------------------
    // Store — domain identity
    // -----------------------------------------------------------------------

    public function test_prime_user_can_create_domain_identity(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'company_name' => 'Acme Corp',
                'domain' => 'acme.com',
            ])
            ->assertRedirect();

        $identity = $user->fresh()->senderIdentity;
        $this->assertNotNull($identity);
        $this->assertEquals('domain', $identity->type);
        $this->assertEquals('Acme Corp', $identity->company_name);
        $this->assertEquals('acme.com', $identity->domain);
        $this->assertNotNull($identity->verification_token);
        $this->assertNull($identity->verified_at);
        $this->assertNull($identity->email);
    }

    public function test_domain_identity_is_not_immediately_verified(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'company_name' => 'Acme Corp',
                'domain' => 'acme.com',
            ]);

        $this->assertFalse($user->fresh()->hasVerifiedSenderIdentity());
    }

    public function test_domain_identity_requires_company_name(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'domain' => 'acme.com',
            ])
            ->assertSessionHasErrors('company_name');
    }

    public function test_domain_identity_requires_valid_domain_format(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'company_name' => 'Acme Corp',
                'domain' => 'not a domain',
            ])
            ->assertSessionHasErrors('domain');
    }

    public function test_email_field_is_prohibited_in_store_request(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'email',
                'email' => 'attacker@evil.com',
            ])
            ->assertSessionHasErrors('email');
    }

    // -----------------------------------------------------------------------
    // Store — update existing identity
    // -----------------------------------------------------------------------

    public function test_updating_domain_with_new_domain_resets_verification(): void
    {
        $user = $this->createPrimeUser();
        $identity = SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'old-token',
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'company_name' => 'Acme Corp',
                'domain' => 'new-acme.com',
            ]);

        $identity->refresh();
        $this->assertEquals('new-acme.com', $identity->domain);
        $this->assertNull($identity->verified_at);
        $this->assertNotEquals('old-token', $identity->verification_token);
    }

    public function test_updating_domain_without_change_keeps_verification(): void
    {
        $user = $this->createPrimeUser();
        $verifiedAt = now()->subDay();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'existing-token',
            'verified_at' => $verifiedAt,
        ]);

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'company_name' => 'Acme Corp Updated',
                'domain' => 'acme.com',
            ]);

        $identity = $user->fresh()->senderIdentity;
        $this->assertNotNull($identity->verified_at);
        $this->assertEquals('existing-token', $identity->verification_token);
    }

    public function test_switching_from_email_to_domain_resets_verification(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), [
                'type' => 'domain',
                'company_name' => 'Acme Corp',
                'domain' => 'acme.com',
            ]);

        $identity = $user->fresh()->senderIdentity;
        $this->assertEquals('domain', $identity->type);
        $this->assertNull($identity->verified_at);
        $this->assertNotNull($identity->verification_token);
    }

    public function test_switching_from_domain_to_email_verifies_immediately(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'some-token',
            'verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('user.sender-identity.store'), ['type' => 'email']);

        $identity = $user->fresh()->senderIdentity;
        $this->assertEquals('email', $identity->type);
        $this->assertNotNull($identity->verified_at);
        $this->assertNull($identity->domain);
        $this->assertNull($identity->verification_token);
    }

    // -----------------------------------------------------------------------
    // Verify
    // -----------------------------------------------------------------------

    public function test_domain_verification_succeeds_when_dns_record_found(): void
    {
        $user = $this->createPrimeUser();
        $identity = SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'flashview-verification-test-token',
            'verified_at' => null,
        ]);

        $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $this->actingAs($user)
            ->post(route('user.sender-identity.verify'))
            ->assertRedirect();

        $this->assertNotNull($identity->fresh()->verified_at);
        $this->assertTrue($user->fresh()->hasVerifiedSenderIdentity());
    }

    public function test_domain_verification_fails_when_dns_record_not_found(): void
    {
        $user = $this->createPrimeUser();
        $identity = SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'flashview-verification-test-token',
            'verified_at' => null,
        ]);

        $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        $this->actingAs($user)
            ->post(route('user.sender-identity.verify'))
            ->assertSessionHasErrors('domain');

        $this->assertNull($identity->fresh()->verified_at);
    }

    public function test_verify_fails_when_no_domain_identity_configured(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->post(route('user.sender-identity.verify'))
            ->assertSessionHasErrors('domain');
    }

    public function test_verify_fails_when_identity_is_email_type(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('user.sender-identity.verify'))
            ->assertSessionHasErrors('domain');
    }

    // -----------------------------------------------------------------------
    // Destroy
    // -----------------------------------------------------------------------

    public function test_prime_user_can_delete_sender_identity(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('user.sender-identity.destroy'))
            ->assertRedirect();

        $this->assertNull($user->fresh()->senderIdentity);
    }

    public function test_destroy_is_no_op_when_no_identity_exists(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('user.sender-identity.destroy'))
            ->assertRedirect();
    }

    // -----------------------------------------------------------------------
    // Secret creation with sender identity snapshot
    // -----------------------------------------------------------------------

    public function test_domain_identity_snapshot_attached_to_secret(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'some-token',
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload())
            ->assertSessionHasNoErrors();

        $secret = $user->secrets()->first();
        $this->assertEquals('Acme Corp', $secret->sender_company_name);
        $this->assertEquals('acme.com', $secret->sender_domain);
        $this->assertNull($secret->sender_email);
    }

    public function test_email_identity_snapshot_attached_to_secret(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload())
            ->assertSessionHasNoErrors();

        $secret = $user->secrets()->first();
        $this->assertNull($secret->sender_company_name);
        $this->assertNull($secret->sender_domain);
        $this->assertEquals($user->email, $secret->sender_email);
    }

    public function test_unverified_domain_identity_not_attached_to_secret(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'some-token',
            'verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload())
            ->assertSessionHasNoErrors();

        $secret = $user->secrets()->first();
        $this->assertNull($secret->sender_company_name);
        $this->assertNull($secret->sender_domain);
        $this->assertNull($secret->sender_email);
    }

    public function test_guest_secret_has_no_sender_snapshot(): void
    {
        $this->post(route('secret.store'), $this->validSecretPayload())
            ->assertSessionHasNoErrors();

        $secret = Secret::withoutGlobalScopes()->latest()->first();
        $this->assertNull($secret->sender_company_name);
        $this->assertNull($secret->sender_domain);
        $this->assertNull($secret->sender_email);
    }

    public function test_removing_identity_does_not_affect_existing_secrets(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        // Create secret with identity attached
        $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload());

        $secretBefore = $user->secrets()->first();
        $senderEmailBefore = $secretBefore->sender_email;

        // Remove identity
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('user.sender-identity.destroy'));

        // Secret snapshot should be unchanged
        $secretBefore->refresh();
        $this->assertEquals($senderEmailBefore, $secretBefore->sender_email);
    }

    // -----------------------------------------------------------------------
    // SecretController::show() returns correct sender props
    // -----------------------------------------------------------------------

    public function test_show_passes_domain_sender_props(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'some-token',
            'verified_at' => now(),
        ]);

        $storeResponse = $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload());

        $storeResponse->assertSessionHas('flash.secret.url');
        $url = $storeResponse->getSession()->get('flash')['secret']['url'];

        $response = $this->get($url);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('senderCompanyName')
            ->has('senderDomain')
            ->has('senderEmail')
        );
    }

    public function test_show_passes_null_sender_props_when_no_identity_on_secret(): void
    {
        $storeResponse = $this->post(route('secret.store'), $this->validSecretPayload());

        $storeResponse->assertSessionHas('flash.secret.url');
        $url = $storeResponse->getSession()->get('flash')['secret']['url'];

        $response = $this->get($url);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('senderCompanyName', null)
            ->where('senderDomain', null)
            ->where('senderEmail', null)
        );
    }

    // -----------------------------------------------------------------------
    // Settings page
    // -----------------------------------------------------------------------

    public function test_settings_page_passes_plan_supports_sender_identity_true_for_prime(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->get(route('user.settings.index'))
            ->assertInertia(fn ($page) => $page
                ->where('planSupportsSenderIdentity', true)
            );
    }

    public function test_settings_page_passes_plan_supports_sender_identity_false_for_basic(): void
    {
        $user = $this->createBasicUser();

        $this->actingAs($user)
            ->get(route('user.settings.index'))
            ->assertInertia(fn ($page) => $page
                ->where('planSupportsSenderIdentity', false)
            );
    }

    public function test_settings_page_passes_sender_identity_data_when_configured(): void
    {
        $user = $this->createPrimeUser();
        SenderIdentity::factory()->for($user)->create([
            'type' => 'email',
            'email' => $user->email,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('user.settings.index'))
            ->assertInertia(fn ($page) => $page
                ->has('senderIdentity')
                ->where('senderIdentity.type', 'email')
                ->where('senderIdentity.is_verified', true)
            );
    }

    public function test_settings_page_passes_null_sender_identity_when_not_configured(): void
    {
        $user = $this->createPrimeUser();

        $this->actingAs($user)
            ->get(route('user.settings.index'))
            ->assertInertia(fn ($page) => $page
                ->where('senderIdentity', null)
            );
    }
}
