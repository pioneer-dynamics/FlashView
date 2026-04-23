<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class CliAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithApiAccess(): User
    {
        $user = User::factory()->withPersonalTeam()->create();

        $plan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
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

    public function test_authorize_page_renders_for_authenticated_user(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->has('port')
            ->has('state')
            ->has('hasApiAccess')
            ->has('availablePermissions')
            ->has('defaultPermissions')
            ->where('port', 12345)
            ->where('state', 'abcdef1234567890')
            ->where('hasApiAccess', true)
        );
    }

    public function test_authorize_page_redirects_to_login_for_unauthenticated_user(): void
    {
        $response = $this->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertRedirect('/login');
    }

    public function test_authorize_page_shows_no_api_access_for_free_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('hasApiAccess', false)
        );
    }

    public function test_authorize_page_validates_port_and_state(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->get('/cli/authorize?port=80&state=abcdef1234567890')
            ->assertSessionHasErrors('port');

        $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=short')
            ->assertSessionHasErrors('state');

        $this->actingAs($user)
            ->get('/cli/authorize')
            ->assertSessionHasErrors(['port', 'state']);
    }

    public function test_authorize_generates_code_and_redirects_to_callback(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->post('/cli/authorize', [
                'port' => 12345,
                'state' => 'abcdef1234567890',
                'action' => 'approve',
            ]);

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('http://127.0.0.1:12345/callback?', $location);

        $parsed = parse_url($location);
        parse_str($parsed['query'], $query);

        $this->assertArrayHasKey('code', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertEquals('abcdef1234567890', $query['state']);
        $this->assertEquals(64, strlen($query['code']));
    }

    public function test_authorize_deny_redirects_with_error(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->post('/cli/authorize', [
                'port' => 12345,
                'state' => 'abcdef1234567890',
                'action' => 'deny',
            ]);

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error=denied', $location);
        $this->assertStringContainsString('state=abcdef1234567890', $location);
    }

    public function test_authorize_redirects_with_no_api_access_error(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)
            ->post('/cli/authorize', [
                'port' => 12345,
                'state' => 'abcdef1234567890',
                'action' => 'approve',
            ]);

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error=no_api_access', $location);
    }

    public function test_authorize_requires_authentication(): void
    {
        $response = $this->post('/cli/authorize', [
            'port' => 12345,
            'state' => 'abcdef1234567890',
            'action' => 'approve',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_exchange_token_successfully(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('x', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
        ], now()->addSeconds(60));

        $response = $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => ['name', 'email'],
        ]);
        $response->assertJsonPath('user.email', $user->email);

        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_exchange_token_is_one_time_use(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('y', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
        ], now()->addSeconds(60));

        $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ])->assertOk();

        $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ])->assertStatus(401);
    }

    public function test_exchange_token_rejects_invalid_code(): void
    {
        $response = $this->postJson('/cli/token', [
            'code' => 'invalid_code_that_does_not_exist',
            'state' => 'abcdef1234567890',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid or expired authorization code.');
    }

    public function test_exchange_token_rejects_state_mismatch(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('z', 54);

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => 'correct_state_value',
        ], now()->addSeconds(60));

        $response = $this->postJson('/cli/token', [
            'code' => $code,
            'state' => 'wrong_state_value1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'State parameter mismatch.');
    }

    public function test_exchange_token_creates_token_with_default_permissions(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('w', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => Jetstream::$defaultPermissions,
        ], now()->addSeconds(60));

        $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ])->assertOk();

        $token = $user->fresh()->tokens->first();
        $this->assertEquals('cli', $token->type);

        foreach (Jetstream::$defaultPermissions as $permission) {
            $this->assertTrue($token->can($permission));
        }
    }

    public function test_exchange_token_creates_token_with_selected_permissions(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('v', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => ['secrets:create', 'secrets:list'],
        ], now()->addSeconds(60));

        $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ])->assertOk();

        $token = $user->fresh()->tokens->first();
        $this->assertTrue($token->can('secrets:create'));
        $this->assertTrue($token->can('secrets:list'));
        $this->assertFalse($token->can('secrets:delete'));
    }

    public function test_exchange_token_preserves_existing_cli_tokens(): void
    {
        $user = $this->createUserWithApiAccess();

        $existingCliToken = $user->createToken('My Laptop', ['secrets:create']);
        $existingCliToken->accessToken->update(['type' => 'cli']);
        $user->createToken('Other Token', ['secrets:create']);
        $this->assertCount(2, $user->fresh()->tokens);

        $code = 'test_code_'.str_repeat('r', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => ['secrets:create', 'secrets:list'],
            'name' => 'Work Desktop',
        ], now()->addSeconds(60));

        $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ])->assertOk();

        $tokens = $user->fresh()->tokens;
        $this->assertCount(3, $tokens);

        $this->assertNotNull($tokens->where('name', 'My Laptop')->first());
        $this->assertNotNull($tokens->where('name', 'Other Token')->first());

        $newCliToken = $tokens->where('name', 'Work Desktop')->first();
        $this->assertNotNull($newCliToken);
        $this->assertEquals('cli', $newCliToken->type);
        $this->assertTrue($newCliToken->can('secrets:create'));
        $this->assertTrue($newCliToken->can('secrets:list'));
    }

    public function test_exchange_token_validates_required_fields(): void
    {
        $this->postJson('/cli/token', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'state']);
    }

    public function test_authorize_page_defaults_to_existing_token_abilities(): void
    {
        $user = $this->createUserWithApiAccess();

        $token = $user->createToken('My Laptop', ['secrets:create', 'secrets:list']);
        $token->accessToken->update(['type' => 'cli']);

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('defaultPermissions', ['secrets:create', 'secrets:list'])
        );
    }

    public function test_authorize_page_defaults_to_jetstream_defaults_when_no_cli_token_exists(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('defaultPermissions', Jetstream::$defaultPermissions)
        );
    }

    public function test_authorize_page_defaults_to_jetstream_defaults_when_only_non_cli_tokens_exist(): void
    {
        $user = $this->createUserWithApiAccess();

        $user->createToken('Other Token', ['secrets:create', 'secrets:list', 'secrets:delete']);

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('defaultPermissions', Jetstream::$defaultPermissions)
        );
    }

    public function test_authorize_page_returns_existing_device_name_when_valid_token_id_sent(): void
    {
        $user = $this->createUserWithApiAccess();

        $token = $user->createToken('My Laptop', ['secrets:create']);
        $token->accessToken->update(['type' => 'cli']);
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($user)
            ->get("/cli/authorize?port=12345&state=abcdef1234567890&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('existingDeviceName', 'My Laptop')
        );
    }

    public function test_authorize_page_returns_null_existing_device_name_when_token_id_not_found(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890&token_id=99999');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('existingDeviceName', null)
        );
    }

    public function test_authorize_page_returns_null_existing_device_name_when_token_id_not_provided(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('existingDeviceName', null)
        );
    }

    public function test_authorize_page_returns_null_existing_device_name_for_non_cli_token(): void
    {
        $user = $this->createUserWithApiAccess();

        // Create a non-CLI (api) token
        $token = $user->createToken('API Token', ['secrets:create']);
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($user)
            ->get("/cli/authorize?port=12345&state=abcdef1234567890&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('existingDeviceName', null)
        );
    }

    public function test_authorize_page_shows_stored_name_even_when_user_renamed_token(): void
    {
        $user = $this->createUserWithApiAccess();

        // Create CLI token with original name, then rename it (simulating rename via API tokens page)
        $token = $user->createToken('Original Name', ['secrets:create']);
        $token->accessToken->update(['type' => 'cli', 'name' => 'Renamed Device']);
        $tokenId = $token->accessToken->id;

        // CLI sends hostname, but token_id points to the renamed token
        $response = $this->actingAs($user)
            ->get("/cli/authorize?port=12345&state=abcdef1234567890&name=some-hostname&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('existingDeviceName', 'Renamed Device')
        );
    }

    public function test_authorize_page_does_not_leak_other_users_token(): void
    {
        $user = $this->createUserWithApiAccess();
        $otherUser = $this->createUserWithApiAccess();

        $token = $otherUser->createToken('Other User Laptop', ['secrets:create']);
        $token->accessToken->update(['type' => 'cli']);
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($user)
            ->get("/cli/authorize?port=12345&state=abcdef1234567890&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('existingDeviceName', null)
        );
    }

    public function test_end_to_end_reauthorization_with_token_id(): void
    {
        $user = $this->createUserWithApiAccess();

        // Step 1: Create existing CLI token (simulates first authorization)
        $existingToken = $user->createToken('MyMachine', ['secrets:create']);
        $existingToken->accessToken->update(['type' => 'cli']);
        $tokenId = $existingToken->accessToken->id;

        // Step 2: GET authorize with token_id — verify existing device detected
        $this->actingAs($user)
            ->get("/cli/authorize?port=12345&state=abcdef1234567890&name=MyMachine.local&token_id={$tokenId}")
            ->assertInertia(fn ($page) => $page
                ->where('existingDeviceName', 'MyMachine')
            );

        // Step 3: POST authorize with the stored name (as the frontend would submit)
        $response = $this->actingAs($user)
            ->post('/cli/authorize', [
                'port' => 12345,
                'state' => 'abcdef1234567890',
                'action' => 'approve',
                'permissions' => ['secrets:create', 'secrets:list'],
                'name' => 'MyMachine',
            ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $parsed = parse_url($location);
        parse_str($parsed['query'], $query);
        $code = $query['code'];

        // Step 4: Exchange the code for a new token
        $exchangeResponse = $this->postJson('/cli/token', [
            'code' => $code,
            'state' => 'abcdef1234567890',
        ]);

        $exchangeResponse->assertOk();
        $exchangeResponse->assertJsonPath('installation_name', 'MyMachine');

        // Step 5: Verify old token was replaced and new token has the stored name
        $tokens = $user->fresh()->tokens()->where('type', 'cli')->get();
        $this->assertCount(1, $tokens);
        $this->assertEquals('MyMachine', $tokens->first()->name);
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

    public function test_authorize_page_validates_port_absent_without_redirect_uri(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->get('/cli/authorize?state=abcdef1234567890')
            ->assertSessionHasErrors(['port', 'redirect_uri']);
    }
}
