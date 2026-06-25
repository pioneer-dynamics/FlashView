<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Jetstream;

beforeEach(function () {
    $this->initiateDevice = function (string $name = 'Test CLI'): array {
        $response = $this->postJson('/cli/device/initiate', ['name' => $name]);
        $response->assertOk();

        return $response->json();
    };
});

test('initiate returns device code and user code', function () {
    $response = $this->postJson('/cli/device/initiate', ['name' => 'My CLI']);

    $response->assertOk();
    $response->assertJsonStructure(['device_code', 'user_code', 'device_url', 'expires_in']);
    expect(strlen($response->json('device_code')))->toEqual(64);
    expect($response->json('expires_in'))->toEqual(900);
    $this->assertStringContainsString('name=My+CLI', $response->json('device_url'));
});

test('user code is formatted as xxxx xxxx', function () {
    $response = $this->postJson('/cli/device/initiate', ['name' => 'My CLI']);

    $response->assertOk();
    expect($response->json('user_code'))->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
});

test('initiate rejects name exceeding 255 chars', function () {
    $longName = str_repeat('a', 300);

    $this->postJson('/cli/device/initiate', ['name' => $longName])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('device page requires authentication', function () {
    $this->get('/cli/device')->assertRedirect('/login');
});

test('device page renders for authenticated user', function () {
    $user = createUserWithApiAccess();

    $this->actingAs($user)
        ->get('/cli/device')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cli/Device')
            ->where('hasApiAccess', true)
            ->has('availablePermissions')
        );
});

test('device page passes suggested name from query string', function () {
    $user = createUserWithApiAccess();

    $this->actingAs($user)
        ->get('/cli/device?name=My+Laptop')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cli/Device')
            ->where('suggestedName', 'My Laptop')
            ->where('existingDeviceName', null)
        );
});

test('device page shows no api access for free user', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->get('/cli/device')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cli/Device')
            ->where('hasApiAccess', false)
        );
});

test('device page includes available permissions', function () {
    $user = createUserWithApiAccess();

    $this->actingAs($user)
        ->get('/cli/device')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cli/Device')
            ->where('availablePermissions', Jetstream::$permissions)
            ->where('defaultPermissions', Jetstream::$defaultPermissions)
        );
});

test('activate requires authentication', function () {
    $this->post('/cli/device', ['user_code' => 'ABCD-1234'])
        ->assertRedirect('/login');
});

test('activate rejects invalid user code format', function () {
    $user = createUserWithApiAccess();

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => 'invalid'])
        ->assertSessionHasErrors('user_code');

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => 'abcd-1234'])
        ->assertSessionHasErrors('user_code');

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => ''])
        ->assertSessionHasErrors('user_code');
});

test('activate rejects unknown user code', function () {
    $user = createUserWithApiAccess();

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => 'ABCD-1234'])
        ->assertSessionHasErrors('user_code');
});

test('activate creates cli token and redirects with success flash', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();

    $response = $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $data['user_code']]);

    $response->assertRedirect(route('cli.device'));
    $response->assertSessionHas('success', true);

    $token = $user->fresh()->tokens()->where('type', 'cli')->first();
    expect($token)->not->toBeNull();
});

test('activate uses selected permissions', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();

    $this->actingAs($user)
        ->post('/cli/device', [
            'user_code' => $data['user_code'],
            'permissions' => ['secrets:create', 'secrets:list'],
        ])
        ->assertRedirect(route('cli.device'));

    $token = $user->fresh()->tokens()->where('type', 'cli')->first();
    expect($token->can('secrets:create'))->toBeTrue();
    expect($token->can('secrets:list'))->toBeTrue();
    expect($token->can('secrets:delete'))->toBeFalse();
});

test('activate uses custom installation name', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();

    $this->actingAs($user)
        ->post('/cli/device', [
            'user_code' => $data['user_code'],
            'name' => 'My Custom Device',
        ])
        ->assertRedirect(route('cli.device'));

    $token = $user->fresh()->tokens()->where('type', 'cli')->where('name', 'My Custom Device')->first();
    expect($token)->not->toBeNull();
});

test('device page shows success flash after prg redirect', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();

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
});

test('activate rejects already used user code', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();
    $userCode = $data['user_code'];

    // First activation should succeed
    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $userCode])
        ->assertRedirect(route('cli.device'));

    // Second activation with same code should fail
    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $userCode])
        ->assertSessionHasErrors('user_code');
});

test('activate marks denied when no api access', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $data = ($this->initiateDevice)();

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $data['user_code']])
        ->assertSessionHasErrors('user_code');

    // Verify cache status updated to denied
    $cachedStatus = Cache::get("cli_device:{$data['device_code']}")['status'];
    expect($cachedStatus)->toEqual('denied');
});

test('poll rejects malformed device code', function () {
    $this->getJson('/cli/device/poll?device_code=short')
        ->assertStatus(401)
        ->assertJsonPath('status', 'expired');

    $this->getJson('/cli/device/poll?device_code=')
        ->assertStatus(401)
        ->assertJsonPath('status', 'expired');

    $this->getJson('/cli/device/poll?device_code='.str_repeat('!', 64))
        ->assertStatus(401)
        ->assertJsonPath('status', 'expired');
});

test('poll returns pending before activation', function () {
    $data = ($this->initiateDevice)();

    $this->getJson('/cli/device/poll?device_code='.$data['device_code'])
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending');
});

test('poll returns authorized and deletes cache entry', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $data['user_code']]);

    $response = $this->getJson('/cli/device/poll?device_code='.$data['device_code']);

    $response->assertOk();
    $response->assertJsonPath('status', 'authorized');
    $response->assertJsonStructure(['token', 'user' => ['name', 'email'], 'installation_name']);
    $response->assertJsonPath('user.email', $user->email);

    // Cache should be deleted immediately after retrieval
    expect(Cache::get("cli_device:{$data['device_code']}"))->toBeNull();
});

test('poll cannot retrieve token twice', function () {
    $user = createUserWithApiAccess();
    $data = ($this->initiateDevice)();

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
});

test('poll returns expired for unknown device code', function () {
    $this->getJson('/cli/device/poll?device_code='.str_repeat('a', 64))
        ->assertStatus(401)
        ->assertJsonPath('status', 'expired');
});

test('poll returns denied for no api access', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $data = ($this->initiateDevice)();

    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $data['user_code']]);

    $this->getJson('/cli/device/poll?device_code='.$data['device_code'])
        ->assertStatus(403)
        ->assertJsonPath('status', 'denied')
        ->assertJsonPath('reason', 'no_api_access');
});

test('existing browser flow is unaffected', function () {
    $user = createUserWithApiAccess();
    $code = 'browser_code_'.str_repeat('x', 51);
    $state = 'abcdef1234567890';

    Cache::put("cli_auth:{$code}", [
        'user_id' => $user->id,
        'state' => $state,
    ], now()->addSeconds(60));

    $this->postJson('/cli/token', ['code' => $code, 'state' => $state])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['name', 'email']]);
});

test('reauthorization via token id preserves name and updates permissions', function () {
    $user = createUserWithApiAccess();

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
    expect($tokens)->toHaveCount(1);
    expect($tokens->first()->can('secrets:delete'))->toBeTrue();
    expect($tokens->first()->can('secrets:create'))->toBeFalse();
});

test('re initiating after denied code succeeds', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $firstData = ($this->initiateDevice)('Denied CLI');

    // Activate with a no-access user — sets denied status
    $this->actingAs($user)
        ->post('/cli/device', ['user_code' => $firstData['user_code']]);

    $this->getJson('/cli/device/poll?device_code='.$firstData['device_code'])
        ->assertStatus(403)
        ->assertJsonPath('status', 'denied');

    // A fresh initiation should work independently
    $secondData = ($this->initiateDevice)('Retry CLI');
    $this->assertNotEquals($firstData['device_code'], $secondData['device_code']);
    $this->assertNotEquals($firstData['user_code'], $secondData['user_code']);

    $this->getJson('/cli/device/poll?device_code='.$secondData['device_code'])
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending');
});
