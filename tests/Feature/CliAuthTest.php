<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Jetstream;

test('authorize page renders for authenticated user', function () {
    $user = createUserWithApiAccess();

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
});

test('authorize page redirects to login for unauthenticated user', function () {
    $response = $this->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertRedirect('/login');
});

test('authorize page shows no api access for free user', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('hasApiAccess', false)
    );
});

test('authorize page validates port and state', function () {
    $user = createUserWithApiAccess();

    $this->actingAs($user)
        ->get('/cli/authorize?port=80&state=abcdef1234567890')
        ->assertSessionHasErrors('port');

    $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=short')
        ->assertSessionHasErrors('state');

    $this->actingAs($user)
        ->get('/cli/authorize')
        ->assertSessionHasErrors(['port', 'state']);
});

test('authorize generates code and redirects to callback', function () {
    $user = createUserWithApiAccess();

    $response = $this->actingAs($user)
        ->post('/cli/authorize', [
            'port' => 12345,
            'state' => 'abcdef1234567890',
            'action' => 'approve',
        ]);

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->not->toBeNull();
    expect($location)->toStartWith('http://127.0.0.1:12345/callback?');

    $parsed = parse_url($location);
    parse_str($parsed['query'], $query);

    expect($query)->toHaveKey('code');
    expect($query)->toHaveKey('state');
    expect($query['state'])->toEqual('abcdef1234567890');
    expect(strlen($query['code']))->toEqual(64);
});

test('authorize deny redirects with error', function () {
    $user = createUserWithApiAccess();

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
});

test('authorize redirects with no api access error', function () {
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
});

test('authorize requires authentication', function () {
    $response = $this->post('/cli/authorize', [
        'port' => 12345,
        'state' => 'abcdef1234567890',
        'action' => 'approve',
    ]);

    $response->assertRedirect('/login');
});

test('exchange token successfully', function () {
    $user = createUserWithApiAccess();
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

    expect($user->fresh()->tokens)->toHaveCount(1);
});

test('exchange token is one time use', function () {
    $user = createUserWithApiAccess();
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
});

test('exchange token rejects invalid code', function () {
    $response = $this->postJson('/cli/token', [
        'code' => 'invalid_code_that_does_not_exist',
        'state' => 'abcdef1234567890',
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('message', 'Invalid or expired authorization code.');
});

test('exchange token rejects state mismatch', function () {
    $user = createUserWithApiAccess();
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
});

test('exchange token creates token with default permissions', function () {
    $user = createUserWithApiAccess();
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
    expect($token->type)->toEqual('cli');

    foreach (Jetstream::$defaultPermissions as $permission) {
        expect($token->can($permission))->toBeTrue();
    }
});

test('exchange token creates token with selected permissions', function () {
    $user = createUserWithApiAccess();
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
    expect($token->can('secrets:create'))->toBeTrue();
    expect($token->can('secrets:list'))->toBeTrue();
    expect($token->can('secrets:delete'))->toBeFalse();
});

test('exchange token preserves existing cli tokens', function () {
    $user = createUserWithApiAccess();

    $existingCliToken = $user->createToken('My Laptop', ['secrets:create']);
    $existingCliToken->accessToken->update(['type' => 'cli']);
    $user->createToken('Other Token', ['secrets:create']);
    expect($user->fresh()->tokens)->toHaveCount(2);

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
    expect($tokens)->toHaveCount(3);

    expect($tokens->where('name', 'My Laptop')->first())->not->toBeNull();
    expect($tokens->where('name', 'Other Token')->first())->not->toBeNull();

    $newCliToken = $tokens->where('name', 'Work Desktop')->first();
    expect($newCliToken)->not->toBeNull();
    expect($newCliToken->type)->toEqual('cli');
    expect($newCliToken->can('secrets:create'))->toBeTrue();
    expect($newCliToken->can('secrets:list'))->toBeTrue();
});

test('exchange token validates required fields', function () {
    $this->postJson('/cli/token', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'state']);
});

test('authorize page defaults to existing token abilities', function () {
    $user = createUserWithApiAccess();

    $token = $user->createToken('My Laptop', ['secrets:create', 'secrets:list']);
    $token->accessToken->update(['type' => 'cli']);

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('defaultPermissions', ['secrets:create', 'secrets:list'])
    );
});

test('authorize page defaults to jetstream defaults when no cli token exists', function () {
    $user = createUserWithApiAccess();

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('defaultPermissions', Jetstream::$defaultPermissions)
    );
});

test('authorize page defaults to jetstream defaults when only non cli tokens exist', function () {
    $user = createUserWithApiAccess();

    $user->createToken('Other Token', ['secrets:create', 'secrets:list', 'secrets:delete']);

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('defaultPermissions', Jetstream::$defaultPermissions)
    );
});

test('authorize page returns existing device name when valid token id sent', function () {
    $user = createUserWithApiAccess();

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
});

test('authorize page returns null existing device name when token id not found', function () {
    $user = createUserWithApiAccess();

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890&token_id=99999');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('existingDeviceName', null)
    );
});

test('authorize page returns null existing device name when token id not provided', function () {
    $user = createUserWithApiAccess();

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('existingDeviceName', null)
    );
});

test('authorize page returns null existing device name for non cli token', function () {
    $user = createUserWithApiAccess();

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
});

test('authorize page shows stored name even when user renamed token', function () {
    $user = createUserWithApiAccess();

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
});

test('authorize page does not leak other users token', function () {
    $user = createUserWithApiAccess();
    $otherUser = createUserWithApiAccess();

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
});

test('end to end reauthorization with token id', function () {
    $user = createUserWithApiAccess();

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
    expect($tokens)->toHaveCount(1);
    expect($tokens->first()->name)->toEqual('MyMachine');
});
