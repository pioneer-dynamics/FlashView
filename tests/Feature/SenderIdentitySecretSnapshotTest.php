<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Secret;
use App\Models\SenderIdentity;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SenderIdentitySecretSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function createPrimeUser(): User
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_snapshot',
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
            'stripe_id' => 'sub_test_basic_snapshot',
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

    public function test_expired_plan_user_with_verified_identity_gets_no_snapshot(): void
    {
        $user = $this->createBasicUser();
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

        $this->actingAs($user)
            ->post(route('secret.store'), $this->validSecretPayload());

        $secretBefore = $user->secrets()->first();
        $senderEmailBefore = $secretBefore->sender_email;

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('user.sender-identity.destroy'));

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
