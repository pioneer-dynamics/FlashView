<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithApiAccess(): User
    {
        $user = User::factory()->withPersonalTeam()->create();

        $plan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime_'.uniqid(),
            'stripe_yearly_price_id' => 'price_yearly_prime_'.uniqid(),
            'stripe_product_id' => 'prod_prime_'.uniqid(),
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_cli_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function createUserWithMobileAccess(): User
    {
        $user = User::factory()->withPersonalTeam()->create();

        $plan = Plan::factory()->create([
            'name' => 'Basic',
            'stripe_monthly_price_id' => 'price_monthly_basic_'.uniqid(),
            'stripe_yearly_price_id' => 'price_yearly_basic_'.uniqid(),
            'stripe_product_id' => 'prod_basic_'.uniqid(),
            'price_per_month' => 10,
            'price_per_year' => 100,
            'features' => ['mobile_app' => ['order' => 8, 'label' => 'Mobile App Access', 'config' => [], 'type' => 'feature']],
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_mobile_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    public function test_mobile_authorize_page_renders_with_redirect_uri(): void
    {
        $user = $this->createUserWithMobileAccess();
        config(['auth.allowed_redirect_uris' => ['https://flashview.link/auth/mobile/callback']]);

        $response = $this->actingAs($user)
            ->get('/cli/authorize?redirect_uri=https://flashview.link/auth/mobile/callback&state=abcdef1234567890&client_type=mobile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('port', null)
            ->where('redirectUri', 'https://flashview.link/auth/mobile/callback')
            ->where('clientType', 'mobile')
            ->where('hasApiAccess', true)
        );
    }

    public function test_mobile_authorize_rejects_unallowed_redirect_uri(): void
    {
        $user = $this->createUserWithApiAccess();
        config(['auth.allowed_redirect_uris' => ['https://flashview.link/auth/mobile/callback']]);

        $response = $this->actingAs($user)
            ->get('/cli/authorize?redirect_uri=https://malicious.example.com/steal&state=abcdef1234567890&client_type=mobile');

        $response->assertSessionHasErrors('redirect_uri');
    }

    public function test_mobile_authorize_generates_code_and_redirects_to_redirect_uri(): void
    {
        $user = $this->createUserWithMobileAccess();
        config(['auth.allowed_redirect_uris' => ['https://flashview.link/auth/mobile/callback']]);

        $response = $this->actingAs($user)
            ->post('/cli/authorize', [
                'redirect_uri' => 'https://flashview.link/auth/mobile/callback',
                'state' => 'abcdef1234567890',
                'client_type' => 'mobile',
                'action' => 'approve',
            ]);

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://flashview.link/auth/mobile/callback?', $location);

        $parsed = parse_url($location);
        parse_str($parsed['query'], $query);

        $this->assertArrayHasKey('code', $query);
        $this->assertEquals('abcdef1234567890', $query['state']);
        $this->assertEquals(64, strlen($query['code']));
    }

    public function test_exchange_token_creates_mobile_typed_token(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('m', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => [],
            'client_type' => 'mobile',
        ], now()->addSeconds(60));

        $response = $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ]);

        $response->assertOk();

        $token = $user->fresh()->tokens->first();
        $this->assertEquals('mobile', $token->type);
        $this->assertStringStartsWith('Mobile Installation', $token->name);
    }

    public function test_mobile_token_default_name_increments_correctly(): void
    {
        $user = $this->createUserWithApiAccess();

        $existingMobile = $user->createToken('Mobile Installation #1', []);
        $existingMobile->accessToken->update(['type' => 'mobile']);

        $code = 'test_code_'.str_repeat('n', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => [],
            'client_type' => 'mobile',
        ], now()->addSeconds(60));

        $response = $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ]);

        $response->assertOk();
        $response->assertJsonPath('installation_name', 'Mobile Installation #2');
    }

    public function test_mobile_reauth_uses_mobile_token_lookup(): void
    {
        $user = $this->createUserWithApiAccess();
        config(['auth.allowed_redirect_uris' => ['https://flashview.link/auth/mobile/callback']]);

        $mobileToken = $user->createToken('My iPhone', ['secrets:create']);
        $mobileToken->accessToken->update(['type' => 'mobile']);
        $tokenId = $mobileToken->accessToken->id;

        $response = $this->actingAs($user)
            ->get("/cli/authorize?redirect_uri=https://flashview.link/auth/mobile/callback&state=abcdef1234567890&client_type=mobile&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('existingDeviceName', 'My iPhone')
        );
    }

    public function test_mobile_reauth_does_not_find_cli_token_by_id(): void
    {
        $user = $this->createUserWithApiAccess();
        config(['auth.allowed_redirect_uris' => ['https://flashview.link/auth/mobile/callback']]);

        $cliToken = $user->createToken('My Laptop', ['secrets:create']);
        $cliToken->accessToken->update(['type' => 'cli']);
        $tokenId = $cliToken->accessToken->id;

        $response = $this->actingAs($user)
            ->get("/cli/authorize?redirect_uri=https://flashview.link/auth/mobile/callback&state=abcdef1234567890&client_type=mobile&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('existingDeviceName', null)
        );
    }
}
