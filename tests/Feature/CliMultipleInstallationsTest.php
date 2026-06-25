<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;

function createCliToken(User $user, string $name = 'Test CLI', array $abilities = ['secrets:create']): void
{
    $token = $user->createToken($name, $abilities);
    $token->accessToken->update(['type' => 'cli']);
}

beforeEach(function () {
    $this->exchangeTokenWithName = function (User $user, ?string $name = null): TestResponse {
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
    };
});

test('user can authorize multiple cli installations', function () {
    $user = createUserWithApiAccess();

    ($this->exchangeTokenWithName)($user, 'Work Laptop')->assertOk();
    ($this->exchangeTokenWithName)($user, 'Home Desktop')->assertOk();
    ($this->exchangeTokenWithName)($user, 'CI Server')->assertOk();

    $cliTokens = $user->fresh()->tokens()->where('type', 'cli')->get();
    expect($cliTokens)->toHaveCount(3);
    expect($cliTokens->pluck('name')->toArray())->toEqualCanonicalizing(['Work Laptop', 'Home Desktop', 'CI Server']);
});

test('re login from same device replaces existing token', function () {
    $user = createUserWithApiAccess();

    ($this->exchangeTokenWithName)($user, 'Work Laptop')->assertOk();
    ($this->exchangeTokenWithName)($user, 'Home Desktop')->assertOk();
    expect($user->fresh()->tokens()->where('type', 'cli')->get())->toHaveCount(2);

    $response = ($this->exchangeTokenWithName)($user, 'Work Laptop');
    $response->assertOk();

    $cliTokens = $user->fresh()->tokens()->where('type', 'cli')->get();
    expect($cliTokens)->toHaveCount(2);
    expect($cliTokens->pluck('name')->toArray())->toEqualCanonicalizing(['Work Laptop', 'Home Desktop']);
});

test('re login does not affect other device tokens', function () {
    $user = createUserWithApiAccess();

    ($this->exchangeTokenWithName)($user, 'Work Laptop')->assertOk();
    ($this->exchangeTokenWithName)($user, 'Home Desktop')->assertOk();

    $homeToken = $user->fresh()->tokens()->where('type', 'cli')->where('name', 'Home Desktop')->first();
    $homeTokenId = $homeToken->id;

    ($this->exchangeTokenWithName)($user, 'Work Laptop')->assertOk();

    expect($user->fresh()->tokens()->find($homeTokenId))->not->toBeNull();
});

test('re login does not affect api tokens', function () {
    $user = createUserWithApiAccess();

    $user->createToken('My API Token', ['secrets:create']);
    ($this->exchangeTokenWithName)($user, 'Work Laptop')->assertOk();
    expect($user->fresh()->tokens)->toHaveCount(2);

    ($this->exchangeTokenWithName)($user, 'Work Laptop')->assertOk();

    $tokens = $user->fresh()->tokens;
    expect($tokens)->toHaveCount(2);
    expect($tokens->where('name', 'My API Token')->first())->not->toBeNull();
});

test('cli token gets type cli', function () {
    $user = createUserWithApiAccess();

    ($this->exchangeTokenWithName)($user, 'My Device')->assertOk();

    $token = $user->fresh()->tokens->first();
    expect($token->type)->toEqual('cli');
});

test('default name is generated when not provided', function () {
    $user = createUserWithApiAccess();

    ($this->exchangeTokenWithName)($user)->assertOk();

    $token = $user->fresh()->tokens->first();
    expect($token->name)->toStartWith('CLI Installation #');
});

test('exchange uses name from cache not request', function () {
    $user = createUserWithApiAccess();
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
    expect($token->name)->toEqual('Cached Name');
});

test('cli tokens visible on api tokens page', function () {
    $user = createUserWithApiAccess();

    createCliToken($user, 'Work Laptop');
    createCliToken($user, 'Home Desktop');
    $user->createToken('API Token', ['secrets:create']);

    $response = $this->actingAs($user)
        ->get('/user/api-tokens');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('API/Index')
        ->has('tokens', 3)
    );
});

test('user can revoke specific cli installation', function () {
    $user = createUserWithApiAccess();

    createCliToken($user, 'Keep This');
    createCliToken($user, 'Delete This');

    $tokens = $user->fresh()->tokens()->where('type', 'cli')->get();
    $tokenToDelete = $tokens->where('name', 'Delete This')->first();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete("/user/cli-installations/{$tokenToDelete->id}");

    $response->assertRedirect();

    $remainingTokens = $user->fresh()->tokens()->where('type', 'cli')->get();
    expect($remainingTokens)->toHaveCount(1);
    expect($remainingTokens->first()->name)->toEqual('Keep This');
});

test('user cannot revoke other users cli installation', function () {
    $user1 = createUserWithApiAccess();
    $user2 = createUserWithApiAccess();

    createCliToken($user1, 'User1 Device');
    $token = $user1->fresh()->tokens()->where('type', 'cli')->first();

    $this->actingAs($user2)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete("/user/cli-installations/{$token->id}")
        ->assertNotFound();

    expect($token->fresh())->not->toBeNull();
});

test('user cannot revoke api token via cli installation route', function () {
    $user = createUserWithApiAccess();

    $apiToken = $user->createToken('API Token', ['secrets:create']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete("/user/cli-installations/{$apiToken->accessToken->id}")
        ->assertNotFound();
});

test('show pre populates permissions from most recent cli token', function () {
    $user = createUserWithApiAccess();

    createCliToken($user, 'Old Device', ['secrets:create']);
    createCliToken($user, 'New Device', ['secrets:create', 'secrets:list', 'secrets:delete']);

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('defaultPermissions', ['secrets:create', 'secrets:list', 'secrets:delete'])
    );
});

test('cli auth flow backward compatible without name', function () {
    $user = createUserWithApiAccess();

    $response = ($this->exchangeTokenWithName)($user);

    $response->assertOk();
    $response->assertJsonStructure([
        'token',
        'user' => ['name', 'email'],
        'installation_name',
    ]);

    $token = $user->fresh()->tokens->first();
    expect($token->type)->toEqual('cli');
    expect($token->name)->toStartWith('CLI Installation #');
});

test('authorize page passes name prop', function () {
    $user = createUserWithApiAccess();

    $response = $this->actingAs($user)
        ->get('/cli/authorize?port=12345&state=abcdef1234567890&name=My+Laptop');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        ->where('name', 'My Laptop')
    );
});

test('authorize stores name in cache', function () {
    $user = createUserWithApiAccess();

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
    expect($cachedData['name'])->toEqual('Work Laptop');
});
