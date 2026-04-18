<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class CliDeviceTest extends TestCase
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
            'stripe_id' => 'sub_test_device_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function initiateDevice(string $name = 'Test CLI'): array
    {
        $response = $this->postJson('/cli/device/initiate', ['name' => $name]);
        $response->assertOk();

        return $response->json();
    }

    // --- initiate ---

    public function test_initiate_returns_device_code_and_user_code(): void
    {
        $response = $this->postJson('/cli/device/initiate', ['name' => 'My CLI']);

        $response->assertOk();
        $response->assertJsonStructure(['device_code', 'user_code', 'device_url', 'expires_in']);
        $this->assertEquals(64, strlen($response->json('device_code')));
        $this->assertEquals(900, $response->json('expires_in'));
        $this->assertStringContainsString('name=My+CLI', $response->json('device_url'));
    }

    public function test_user_code_is_formatted_as_xxxx_xxxx(): void
    {
        $response = $this->postJson('/cli/device/initiate', ['name' => 'My CLI']);

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $response->json('user_code'));
    }

    public function test_initiate_rejects_name_exceeding_255_chars(): void
    {
        $longName = str_repeat('a', 300);

        $this->postJson('/cli/device/initiate', ['name' => $longName])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    // --- show (GET /cli/device) ---

    public function test_device_page_requires_authentication(): void
    {
        $this->get('/cli/device')->assertRedirect('/login');
    }

    public function test_device_page_renders_for_authenticated_user(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->get('/cli/device')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cli/Device')
                ->where('hasApiAccess', true)
                ->has('availablePermissions')
            );
    }

    public function test_device_page_passes_suggested_name_from_query_string(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->get('/cli/device?name=My+Laptop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cli/Device')
                ->where('suggestedName', 'My Laptop')
                ->where('existingDeviceName', null)
            );
    }

    public function test_device_page_shows_no_api_access_for_free_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get('/cli/device')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cli/Device')
                ->where('hasApiAccess', false)
            );
    }

    public function test_device_page_includes_available_permissions(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->get('/cli/device')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cli/Device')
                ->where('availablePermissions', Jetstream::$permissions)
                ->where('defaultPermissions', Jetstream::$defaultPermissions)
            );
    }

    // --- activate (POST /cli/device) ---

    public function test_activate_requires_authentication(): void
    {
        $this->post('/cli/device', ['user_code' => 'ABCD-1234'])
            ->assertRedirect('/login');
    }

    public function test_activate_rejects_invalid_user_code_format(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => 'invalid'])
            ->assertSessionHasErrors('user_code');

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => 'abcd-1234'])
            ->assertSessionHasErrors('user_code');

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => ''])
            ->assertSessionHasErrors('user_code');
    }

    public function test_activate_rejects_unknown_user_code(): void
    {
        $user = $this->createUserWithApiAccess();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => 'ABCD-1234'])
            ->assertSessionHasErrors('user_code');
    }

    public function test_activate_creates_cli_token_and_redirects_with_success_flash(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();

        $response = $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $data['user_code']]);

        $response->assertRedirect(route('cli.device'));
        $response->assertSessionHas('success', true);

        $token = $user->fresh()->tokens()->where('type', 'cli')->first();
        $this->assertNotNull($token);
    }

    public function test_activate_uses_selected_permissions(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', [
                'user_code' => $data['user_code'],
                'permissions' => ['secrets:create', 'secrets:list'],
            ])
            ->assertRedirect(route('cli.device'));

        $token = $user->fresh()->tokens()->where('type', 'cli')->first();
        $this->assertTrue($token->can('secrets:create'));
        $this->assertTrue($token->can('secrets:list'));
        $this->assertFalse($token->can('secrets:delete'));
    }

    public function test_activate_uses_custom_installation_name(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', [
                'user_code' => $data['user_code'],
                'name' => 'My Custom Device',
            ])
            ->assertRedirect(route('cli.device'));

        $token = $user->fresh()->tokens()->where('type', 'cli')->where('name', 'My Custom Device')->first();
        $this->assertNotNull($token);
    }

    public function test_device_page_shows_success_flash_after_prg_redirect(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $data['user_code']])
            ->assertRedirect(route('cli.device'));

        $this->actingAs($user)
            ->get('/cli/device')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cli/Device')
                ->where('flash.success', true)
            );
    }

    public function test_activate_rejects_already_used_user_code(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();
        $userCode = $data['user_code'];

        // First activation should succeed
        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $userCode])
            ->assertRedirect(route('cli.device'));

        // Second activation with same code should fail
        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $userCode])
            ->assertSessionHasErrors('user_code');
    }

    public function test_activate_marks_denied_when_no_api_access(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $data['user_code']])
            ->assertSessionHasErrors('user_code');

        // Verify cache status updated to denied
        $cachedStatus = Cache::get("cli_device:{$data['device_code']}")['status'];
        $this->assertEquals('denied', $cachedStatus);
    }

    // --- poll (GET /cli/device/poll) ---

    public function test_poll_rejects_malformed_device_code(): void
    {
        $this->getJson('/cli/device/poll?device_code=short')
            ->assertStatus(401)
            ->assertJsonPath('status', 'expired');

        $this->getJson('/cli/device/poll?device_code=')
            ->assertStatus(401)
            ->assertJsonPath('status', 'expired');

        $this->getJson('/cli/device/poll?device_code='.str_repeat('!', 64))
            ->assertStatus(401)
            ->assertJsonPath('status', 'expired');
    }

    public function test_poll_returns_pending_before_activation(): void
    {
        $data = $this->initiateDevice();

        $this->getJson('/cli/device/poll?device_code='.$data['device_code'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');
    }

    public function test_poll_returns_authorized_and_deletes_cache_entry(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $data['user_code']]);

        $response = $this->getJson('/cli/device/poll?device_code='.$data['device_code']);

        $response->assertOk();
        $response->assertJsonPath('status', 'authorized');
        $response->assertJsonStructure(['token', 'user' => ['name', 'email'], 'installation_name']);
        $response->assertJsonPath('user.email', $user->email);

        // Cache should be deleted immediately after retrieval
        $this->assertNull(Cache::get("cli_device:{$data['device_code']}"));
    }

    public function test_poll_cannot_retrieve_token_twice(): void
    {
        $user = $this->createUserWithApiAccess();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $data['user_code']]);

        // First poll retrieves token
        $this->getJson('/cli/device/poll?device_code='.$data['device_code'])
            ->assertOk()
            ->assertJsonPath('status', 'authorized');

        // Second poll sees no cache entry → expired
        $this->getJson('/cli/device/poll?device_code='.$data['device_code'])
            ->assertStatus(401)
            ->assertJsonPath('status', 'expired');
    }

    public function test_poll_returns_expired_for_unknown_device_code(): void
    {
        $this->getJson('/cli/device/poll?device_code='.str_repeat('a', 64))
            ->assertStatus(401)
            ->assertJsonPath('status', 'expired');
    }

    public function test_poll_returns_denied_for_no_api_access(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $data = $this->initiateDevice();

        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $data['user_code']]);

        $this->getJson('/cli/device/poll?device_code='.$data['device_code'])
            ->assertStatus(403)
            ->assertJsonPath('status', 'denied')
            ->assertJsonPath('reason', 'no_api_access');
    }

    // --- combined flows ---

    public function test_existing_browser_flow_is_unaffected(): void
    {
        $user = $this->createUserWithApiAccess();
        $code = 'browser_code_'.str_repeat('x', 51);
        $state = 'abcdef1234567890';

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $state,
        ], now()->addSeconds(60));

        $this->postJson('/cli/token', ['code' => $code, 'state' => $state])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['name', 'email']]);
    }

    public function test_reauthorization_via_token_id_preserves_name_and_updates_permissions(): void
    {
        $user = $this->createUserWithApiAccess();

        // Create an existing CLI token with specific permissions and a known name
        $existingToken = $user->createToken('My Laptop', ['secrets:create', 'secrets:list']);
        $existingToken->accessToken->update(['type' => 'cli']);
        $tokenId = $existingToken->accessToken->id;

        // Initiate with token_id — server should store it and append it to device_url
        $initResponse = $this->postJson('/cli/device/initiate', [
            'name' => 'My Laptop',
            'token_id' => $tokenId,
        ]);
        $initResponse->assertOk();
        $this->assertStringContainsString("token_id={$tokenId}", $initResponse->json('device_url'));

        // Show page with token_id — should pre-fill existing device name and its permissions
        $this->actingAs($user)
            ->get("/cli/device?token_id={$tokenId}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cli/Device')
                ->where('existingDeviceName', 'My Laptop')
                ->where('defaultPermissions', ['secrets:create', 'secrets:list'])
            );

        // Activate with new permissions — old token replaced, new one created
        $this->actingAs($user)
            ->post('/cli/device', [
                'user_code' => $initResponse->json('user_code'),
                'permissions' => ['secrets:delete'],
            ])
            ->assertRedirect(route('cli.device'));

        $user->refresh();
        $tokens = $user->tokens()->where('type', 'cli')->get();
        $this->assertCount(1, $tokens);
        $this->assertTrue($tokens->first()->can('secrets:delete'));
        $this->assertFalse($tokens->first()->can('secrets:create'));
    }

    public function test_re_initiating_after_denied_code_succeeds(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $firstData = $this->initiateDevice('Denied CLI');

        // Activate with a no-access user — sets denied status
        $this->actingAs($user)
            ->post('/cli/device', ['user_code' => $firstData['user_code']]);

        $this->getJson('/cli/device/poll?device_code='.$firstData['device_code'])
            ->assertStatus(403)
            ->assertJsonPath('status', 'denied');

        // A fresh initiation should work independently
        $secondData = $this->initiateDevice('Retry CLI');
        $this->assertNotEquals($firstData['device_code'], $secondData['device_code']);
        $this->assertNotEquals($firstData['user_code'], $secondData['user_code']);

        $this->getJson('/cli/device/poll?device_code='.$secondData['device_code'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');
    }
}
