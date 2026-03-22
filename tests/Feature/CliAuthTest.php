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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

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
        $this->assertEquals('FlashView CLI', $token->name);

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

    public function test_exchange_token_validates_required_fields(): void
    {
        $this->postJson('/cli/token', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'state']);
    }
}
