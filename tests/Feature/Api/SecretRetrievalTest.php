<?php

use App\Exceptions\InvalidHashIdException;
use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->primePlan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => [
            'messages' => [
                'order' => 2,
                'label' => ':message_length character limit per message',
                'config' => ['message_length' => 100000],
                'type' => 'feature',
            ],
            'expiry' => [
                'order' => 3,
                'label' => 'Maximum expiry of :expiry_label',
                'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200],
                'type' => 'feature',
            ],
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => ['email' => true],
                'type' => 'feature',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => ['webhook' => false],
                'type' => 'missing',
            ],
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => 'feature',
            ],
        ],
    ]);

    $this->user = User::factory()->withPersonalTeam()->create();
});

/**
 * Allow the `retrieved` Eloquent event to fire during HTTP tests.
 *
 * The event skips when `App::runningInConsole()` is true, which is
 * always true during PHPUnit. This override simulates real HTTP
 * behaviour so the event marks secrets as retrieved.
 */
function simulateHttpMode(): void
{
    (new ReflectionProperty(app(), 'isRunningInConsole'))->setValue(app(), false);
}

test('can retrieve secret message', function () {
    simulateHttpMode();
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['hash_id', 'message'],
        ])
        ->assertJson([
            'data' => [
                'hash_id' => $secret->hash_id,
            ],
        ]);

    expect($response->json('data.message'))->not->toBeNull();
});

test('secret is marked as retrieved after access', function () {
    simulateHttpMode();
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($freshSecret->message)->toBeNull();
    expect($freshSecret->retrieved_at)->not->toBeNull();
});

test('subsequent retrieval returns 404', function () {
    simulateHttpMode();
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve")
        ->assertOk();

    $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve")
        ->assertNotFound();
});

test('expired secret returns 404', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user, [
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertNotFound();
});

test('nonexistent hash id throws model not found', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);
    $hashId = $secret->hash_id;
    $secret->forceDelete();

    $this->withoutExceptionHandling();

    $this->expectException(ModelNotFoundException::class);

    $this->getJson("/api/v1/secrets/{$hashId}/retrieve");
});

test('unauthenticated request returns 401', function () {
    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertUnauthorized();
});

test('user without api access returns 403', function () {
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertForbidden();
});

test('any authenticated user can retrieve others secret', function () {
    simulateHttpMode();
    $otherUser = User::factory()->withPersonalTeam()->create();
    subscribeUserToPlan($otherUser, $this->primePlan);
    Sanctum::actingAs($otherUser, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['hash_id', 'message'],
        ]);
});

test('already retrieved secret returns 404', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user, [
        'retrieved_at' => now()->subHour(),
        'message' => null,
    ]);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertNotFound();
});

test('token without list ability returns 403', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertForbidden();
});

/**
 * @return array<string, array{string}>
 */
dataset('invalidHashIdProvider', function () {
    return [
        'garbage string' => ['invalid-hash'],
        'special characters' => ['!!!@@@'],
        'random alphanumeric' => ['abc123xyz'],
    ];
});

test('invalid hash id returns 404', function (string $invalidHashId) {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $response = $this->getJson("/api/v1/secrets/{$invalidHashId}/retrieve");

    $response->assertNotFound();
})->with('invalidHashIdProvider');

test('invalid hash id throws invalid hash id exception', function (string $invalidHashId) {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $this->withoutExceptionHandling();

    $this->expectException(InvalidHashIdException::class);

    $this->getJson("/api/v1/secrets/{$invalidHashId}/retrieve");
})->with('invalidHashIdProvider');

test('forbidden request does not consume secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $secret = createSecretForUser($this->user);

    $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve")
        ->assertForbidden();

    $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($freshSecret->message)->not->toBeNull();
    expect($freshSecret->retrieved_at)->toBeNull();
});

test('retrieve returns file metadata for file secret without consuming it', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = Secret::factory()->fileSecret()->create(['user_id' => $this->user->id]);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertOk()
        ->assertJsonPath('data.type', 'file')
        ->assertJsonPath('data.hash_id', $secret->hash_id)
        ->assertJsonStructure(['data' => ['hash_id', 'type', 'file_size', 'file_mime_type']]);

    $fresh = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($fresh->filepath)->not->toBeNull('File should NOT be consumed by /retrieve for file secrets');
    expect($fresh->retrieved_at)->toBeNull();
});

test('api file download redirects to presigned url and marks retrieved', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:retrieve']);

    Storage::fake();
    $filepath = 'secrets/test-file.bin';
    Storage::put($filepath, 'encrypted-binary-content');

    $secret = Secret::factory()->fileSecret($filepath)->create(['user_id' => $this->user->id]);

    $response = $this->get("/api/v1/secrets/{$secret->hash_id}/file");

    // On a local disk that supports temporaryUrl (Storage::fake), we expect a redirect.
    $response->assertRedirect();

    $fresh = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($fresh->retrieved_at)->not->toBeNull('Secret should be marked as retrieved');
});

test('api file download falls back to streaming on local disk', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:retrieve']);

    Storage::fake();
    $filepath = 'secrets/test-file-stream.bin';
    Storage::put($filepath, 'encrypted-binary-content');

    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->andThrow(new RuntimeException('Local driver does not support temporary URLs.'));

    Storage::shouldReceive('get')->with($filepath)->andReturn('encrypted-binary-content');
    Storage::shouldReceive('delete')->with($filepath)->once();

    $secret = Secret::factory()->fileSecret($filepath)->create(['user_id' => $this->user->id]);

    $response = $this->get("/api/v1/secrets/{$secret->hash_id}/file");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream');

    expect($response->streamedContent())->toEqual('encrypted-binary-content');

    $fresh = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($fresh->filepath)->toBeNull();
    expect($fresh->retrieved_at)->not->toBeNull();
});

test('api file download returns 410 on second attempt', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:retrieve']);

    Storage::fake();
    $filepath = 'secrets/test-file-2.bin';
    Storage::put($filepath, 'data');

    $secret = Secret::factory()->fileSecret($filepath)->create(['user_id' => $this->user->id]);

    // First attempt — marks retrieved (redirect or stream)
    $this->get("/api/v1/secrets/{$secret->hash_id}/file");

    // Second attempt — retrieved_at is set, so returns 410
    $this->get("/api/v1/secrets/{$secret->hash_id}/file")->assertStatus(410);
});

test('retrieve returns combined type with message and file metadata', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    Storage::fake();
    $filepath = 'secrets/combined.bin';
    Storage::put($filepath, 'content');

    $secret = Secret::factory()->fileSecret($filepath)->create([
        'user_id' => $this->user->id,
        'message' => 'encrypted-note',
    ]);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}/retrieve");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'type' => 'combined',
                'message' => 'encrypted-note',
                'file_size' => $secret->file_size,
            ],
        ]);

    // Message is consumed (nulled) after retrieve; file still present for download.
    $fresh = Secret::withoutGlobalScopes()->find($secret->id);
    expect($fresh->message)->toBeNull();
    expect($fresh->filepath)->not->toBeNull();
});

test('burn file secret deletes file from storage', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:delete']);

    Storage::fake();
    $filepath = 'secrets/burn-me.bin';
    Storage::put($filepath, 'encrypted-content');

    $secret = Secret::factory()->fileSecret($filepath)->create(['user_id' => $this->user->id]);

    $this->deleteJson("/api/v1/secrets/{$secret->hash_id}")
        ->assertOk();

    Storage::assertMissing($filepath);

    $fresh = Secret::withoutGlobalScopes()->find($secret->id);
    expect($fresh->retrieved_at)->not->toBeNull();
    expect($fresh->filepath)->toBeNull();
});

test('confirm file downloaded nulls file fields', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:retrieve']);

    Storage::fake();
    $filepath = 'secrets/test-confirm.bin';
    Storage::put($filepath, 'data');

    $secret = Secret::factory()->fileSecret($filepath)->create([
        'user_id' => $this->user->id,
        'retrieved_at' => now(),
    ]);

    $this->postJson("/api/v1/secrets/{$secret->hash_id}/file/downloaded")
        ->assertNoContent();

    $fresh = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($fresh->filepath)->toBeNull();
    expect($fresh->file_size)->toBeNull();
    expect($fresh->file_mime_type)->toBeNull();
    Storage::assertMissing($filepath);
});
