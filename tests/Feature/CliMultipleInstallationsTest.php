<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class CliMultipleInstallationsTest extends TestCase
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
            'stripe_id' => 'sub_test_cli_multi_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function createCliToken(User $user, string $name = 'Test CLI', array $abilities = ['secrets:create']): void
    {
        $token = $user->createToken($name, $abilities);
        $token->accessToken->update(['type' => 'cli']);
    }

    private function exchangeTokenWithName(User $user, ?string $name = null): TestResponse
    {
        $code = 'test_code_'.str_repeat(chr(rand(97, 122)), 54);
        $state = 'abcdef1234567890';

        $cacheData = [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => Jetstream::$defaultPermissions,
        ];

        if ($name !== null) {
            $cacheData['name'] = $name;
        }

        Cache::put("cli_auth:{$code}", $cacheData, now()->addSeconds(60));

        return $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ]);
    }

    public function test_user_can_authorize_multiple_cli_installations(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->exchangeTokenWithName($user, 'Work Laptop')->assertOk();
        $this->exchangeTokenWithName($user, 'Home Desktop')->assertOk();
        $this->exchangeTokenWithName($user, 'CI Server')->assertOk();

        $cliTokens = $user->fresh()->tokens()->where('type', 'cli')->get();
        $this->assertCount(3, $cliTokens);
        $this->assertEqualsCanonicalizing(
            ['Work Laptop', 'Home Desktop', 'CI Server'],
            $cliTokens->pluck('name')->toArray()
        );
    }

    public function test_cli_token_gets_type_cli(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->exchangeTokenWithName($user, 'My Device')->assertOk();

        $token = $user->fresh()->tokens->first();
        $this->assertEquals('cli', $token->type);
    }

    public function test_default_name_is_generated_when_not_provided(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->exchangeTokenWithName($user)->assertOk();

        $token = $user->fresh()->tokens->first();
        $this->assertStringStartsWith('CLI Installation #', $token->name);
    }

    public function test_exchange_uses_name_from_cache_not_request(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'test_code_'.str_repeat('a', 54);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
            'permissions' => Jetstream::$defaultPermissions,
            'name' => 'Cached Name',
        ], now()->addSeconds(60));

        $response = $this->postJson('/cli/token', [
            'code' => $code,
            'state' => $state,
        ]);

        $response->assertOk();
        $response->assertJsonPath('installation_name', 'Cached Name');

        $token = $user->fresh()->tokens->first();
        $this->assertEquals('Cached Name', $token->name);
    }

    public function test_user_can_list_cli_installations(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->createCliToken($user, 'Work Laptop');
        $this->createCliToken($user, 'Home Desktop');
        $user->createToken('API Token', ['secrets:create']);

        $response = $this->actingAs($user)
            ->get('/user/cli-installations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('CliInstallations/Index')
            ->has('installations', 2)
            ->has('availablePermissions')
        );
    }

    public function test_user_can_rename_cli_installation(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->createCliToken($user, 'Old Name');
        $token = $user->fresh()->tokens()->where('type', 'cli')->first();

        $response = $this->actingAs($user)
            ->put("/user/cli-installations/{$token->id}", [
                'name' => 'New Name',
            ]);

        $response->assertRedirect();
        $this->assertEquals('New Name', $token->fresh()->name);
    }

    public function test_user_can_revoke_specific_cli_installation(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->createCliToken($user, 'Keep This');
        $this->createCliToken($user, 'Delete This');

        $tokens = $user->fresh()->tokens()->where('type', 'cli')->get();
        $tokenToDelete = $tokens->where('name', 'Delete This')->first();

        $response = $this->actingAs($user)
            ->delete("/user/cli-installations/{$tokenToDelete->id}");

        $response->assertRedirect();

        $remainingTokens = $user->fresh()->tokens()->where('type', 'cli')->get();
        $this->assertCount(1, $remainingTokens);
        $this->assertEquals('Keep This', $remainingTokens->first()->name);
    }

    public function test_user_cannot_manage_other_users_cli_installation(): void
    {
        $user1 = $this->createUserWithApiAccess();
        $user2 = $this->createUserWithApiAccess();

        $this->createCliToken($user1, 'User1 Device');
        $token = $user1->fresh()->tokens()->where('type', 'cli')->first();

        $this->actingAs($user2)
            ->put("/user/cli-installations/{$token->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($user2)
            ->delete("/user/cli-installations/{$token->id}")
            ->assertNotFound();

        $this->assertEquals('User1 Device', $token->fresh()->name);
    }

    public function test_user_cannot_manage_api_token_via_cli_installation_routes(): void
    {
        $user = $this->createUserWithApiAccess();

        $apiToken = $user->createToken('API Token', ['secrets:create']);

        $this->actingAs($user)
            ->put("/user/cli-installations/{$apiToken->accessToken->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($user)
            ->delete("/user/cli-installations/{$apiToken->accessToken->id}")
            ->assertNotFound();
    }

    public function test_show_pre_populates_permissions_from_most_recent_cli_token(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->createCliToken($user, 'Old Device', ['secrets:create']);
        $this->createCliToken($user, 'New Device', ['secrets:create', 'secrets:list', 'secrets:delete']);

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('defaultPermissions', ['secrets:create', 'secrets:list', 'secrets:delete'])
        );
    }

    public function test_cli_auth_flow_backward_compatible_without_name(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->exchangeTokenWithName($user);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => ['name', 'email'],
            'installation_name',
        ]);

        $token = $user->fresh()->tokens->first();
        $this->assertEquals('cli', $token->type);
        $this->assertStringStartsWith('CLI Installation #', $token->name);
    }

    public function test_authorize_page_passes_name_prop(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->get('/cli/authorize?port=12345&state=abcdef1234567890&name=My+Laptop');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            ->where('name', 'My Laptop')
        );
    }

    public function test_authorize_stores_name_in_cache(): void
    {
        $user = $this->createUserWithApiAccess();

        $response = $this->actingAs($user)
            ->post('/cli/authorize', [
                'port' => 12345,
                'state' => 'abcdef1234567890',
                'action' => 'approve',
                'name' => 'Work Laptop',
            ]);

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $parsed = parse_url($location);
        parse_str($parsed['query'], $query);

        $cachedData = Cache::get("cli_auth:{$query['code']}");
        $this->assertEquals('Work Laptop', $cachedData['name']);
    }

    public function test_rename_validation_requires_name(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->createCliToken($user, 'My Device');
        $token = $user->fresh()->tokens()->where('type', 'cli')->first();

        $this->actingAs($user)
            ->put("/user/cli-installations/{$token->id}", ['name' => ''])
            ->assertSessionHasErrors('name');
    }
}
