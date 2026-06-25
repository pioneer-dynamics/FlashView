<?php

use App\Jobs\ClearExpiredSecrets;
use App\Jobs\PurgeMetadataForExpiredMessages;
use App\Models\Scopes\ActiveScope;
use App\Models\Secret;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('full lifecycle create and retrieve', function () {
    $message = (new SecretFactory)->generateEncryptedMessage(50);

    $response = $this->post(route('secret.store'), [
        'message' => $message,
        'expires_in' => 5,
    ]);

    $response->assertSessionHas('flash.secret.url');

    $secret = Secret::first();
    expect($secret)->not->toBeNull();
    expect($secret->message)->not->toBeNull();

    // Retrieve via decrypt route — returns the message as flash data
    $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);
    $response = $this->get($decryptUrl);
    $response->assertRedirect();

    // Note: The Secret::retrieved event skips in console (App::runningInConsole()),
    // so message is NOT auto-cleared during tests. Test the clearing via explicit job instead.
});

test('manual retrieval clears message', function () {
    $secret = Secret::factory()->create();
    expect($secret->message)->not->toBeNull();

    $secret->markAsRetrieved();

    $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
    expect($secret->message)->toBeNull();
    expect($secret->retrieved_at)->not->toBeNull();
});

test('expiry lifecycle create expire clear prune', function () {
    $secret = Secret::factory()->create([
        'expires_at' => now()->addMinutes(5),
    ]);

    expect($secret->message)->not->toBeNull();

    $this->travel(10)->minutes();

    (new ClearExpiredSecrets)->handle();

    $secret = Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id);
    expect($secret->message)->toBeNull();
    expect($secret)->not->toBeNull();

    $this->travel(config('secrets.prune_after') + 1)->days();

    (new PurgeMetadataForExpiredMessages)->handle();

    expect(Secret::withoutGlobalScope(ActiveScope::class)->find($secret->id))->toBeNull();
});

test('guest secret lifecycle no notifications', function () {
    Notification::fake();

    $message = (new SecretFactory)->generateEncryptedMessage(50);
    $this->post(route('secret.store'), [
        'message' => $message,
        'expires_in' => 5,
    ]);

    $secret = Secret::first();
    $decryptUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);
    $this->get($decryptUrl);

    Notification::assertNothingSent();
});
