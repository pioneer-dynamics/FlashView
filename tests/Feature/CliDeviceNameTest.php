<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class CliDeviceNameTest extends TestCase
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
}
