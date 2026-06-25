<?php

use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function generateKeyPairAndSession(): array
{
    $keyPair = sodium_crypto_sign_keypair();
    $privateKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);

    $session = CallSession::factory()->create([
        'public_key' => base64_encode($publicKey),
    ]);

    return [$privateKey, $publicKey, $session];
}

function signChallenge(string $privateKey, string $challengeHex): string
{
    $signature = sodium_crypto_sign_detached(hex2bin($challengeHex), $privateKey);

    return base64_encode($signature);
}

beforeEach(function () {
    $this->fetchChallenge = function (CallSession $session): string {
        $response = $this->getJson("/call-sessions/{$session->hash_id}/challenge");
        $response->assertOk();

        return $response->json('challenge');
    };
});

test('can join with correct signature', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);
    $signature = signChallenge($privateKey, $challenge);

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => $signature,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'session' => ['bridge_number', 'starts_at', 'ends_at', 'max_participants', 'current_participant_count'],
            'participant_id',
            'ice_servers',
            'turn_available',
        ]);
});

test('cannot join with wrong signature', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    ($this->fetchChallenge)($session);

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => base64_encode(random_bytes(64)),
    ]);

    $response->assertStatus(401)->assertJsonPath('message', 'Unauthorised');
});

test('join returns 422 when no challenge exists', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => base64_encode(random_bytes(64)),
    ]);

    $response->assertStatus(422)->assertJsonPath('message', fn ($v) => str_contains($v, 'Challenge expired'));
});

test('join prevents challenge replay', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);
    $signature = signChallenge($privateKey, $challenge);

    // First join succeeds
    $this->postJson("/call-sessions/{$session->hash_id}/join", ['signature' => $signature])
        ->assertOk();

    // Second join with same signature fails — challenge was consumed
    $this->postJson("/call-sessions/{$session->hash_id}/join", ['signature' => $signature])
        ->assertStatus(422);
});

test('join returns ice servers from turn service', function () {
    // Flashview provider generates credentials locally (no HTTP) — returns turn: + stun: entries
    config([
        'turn.default' => 'flashview',
        'turn.drivers.flashview' => ['host' => 'turn.flashview.io', 'auth_secret' => 'testsecret'],
    ]);

    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => signChallenge($privateKey, $challenge),
    ]);

    $response->assertOk()
        ->assertJsonPath('turn_available', true)
        ->assertJsonCount(2, 'ice_servers');
    // turn: entry + stun: entry
});

test('join returns empty ice servers when turn service fails', function () {
    // Switch to metered driver so we can simulate a remote API failure via Http::fake
    config([
        'turn.default' => 'metered',
        'turn.drivers.metered' => ['domain' => 'testapp', 'api_key' => 'k'],
    ]);

    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);

    Http::fake(['*.metered.ca/*' => Http::response('error', 500)]);

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => signChallenge($privateKey, $challenge),
    ]);

    $response->assertOk()
        ->assertJsonPath('turn_available', false)
        ->assertJsonPath('ice_servers', []);
});

test('join creates participant record', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);

    $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => signChallenge($privateKey, $challenge),
    ])->assertOk();

    expect($session->fresh()->participants)->toHaveCount(1);
});

test('join returns 404 for unknown hash id', function () {
    $response = $this->postJson('/call-sessions/unknownhash/join', [
        'signature' => base64_encode(random_bytes(64)),
    ]);

    $response->assertNotFound();
});

test('join requires signature field', function () {
    $session = CallSession::factory()->create();

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", []);

    $response->assertStatus(422)->assertJsonValidationErrors(['signature']);
});

test('join response includes hash id not integer pk', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);

    $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => signChallenge($privateKey, $challenge),
    ])->assertOk();

    $bridgeNumber = $response->json('session.bridge_number');
    $this->assertNotEquals((string) $session->id, $bridgeNumber);
    expect($bridgeNumber)->toEqual($session->hash_id);
});

test('join stores public key when provided', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);
    $signature = signChallenge($privateKey, $challenge);

    // Synthetic placeholder — not a real P-256 JWK; tests DB persistence only
    $publicKey = base64_encode(random_bytes(65));

    $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => $signature,
        'public_key' => $publicKey,
    ])->assertOk();

    $this->assertDatabaseHas('call_participants', [
        'call_session_id' => $session->id,
        'public_key' => $publicKey,
    ]);
});

test('join succeeds when public key is omitted', function () {
    [$privateKey, , $session] = generateKeyPairAndSession();
    $challenge = ($this->fetchChallenge)($session);
    $signature = signChallenge($privateKey, $challenge);

    $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => $signature,
    ])->assertOk();

    $participant = $session->fresh()->participants->first();
    expect($participant->public_key)->toBeNull();
});

test('join rejects oversized public key', function () {
    $session = CallSession::factory()->create();

    $this->postJson("/call-sessions/{$session->hash_id}/join", [
        'signature' => base64_encode(random_bytes(64)),
        'public_key' => str_repeat('A', 513),
    ])->assertUnprocessable()->assertJsonValidationErrors(['public_key']);
});
