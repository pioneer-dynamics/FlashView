<?php

namespace Tests\Feature\Call;

use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JoinCallSessionTest extends TestCase
{
    use RefreshDatabase;

    private function generateKeyPairAndSession(): array
    {
        $keyPair = sodium_crypto_sign_keypair();
        $privateKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);

        $session = CallSession::factory()->create([
            'public_key' => base64_encode($publicKey),
        ]);

        return [$privateKey, $publicKey, $session];
    }

    private function fetchChallenge(CallSession $session): string
    {
        $response = $this->getJson("/call-sessions/{$session->hash_id}/challenge");
        $response->assertOk();

        return $response->json('challenge');
    }

    private function signChallenge(string $privateKey, string $challengeHex): string
    {
        $signature = sodium_crypto_sign_detached(hex2bin($challengeHex), $privateKey);

        return base64_encode($signature);
    }

    public function test_can_join_with_correct_signature(): void
    {
        Http::fake(['*' => Http::response([['urls' => 'stun:stun.example.com']], 200)]);

        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $challenge = $this->fetchChallenge($session);
        $signature = $this->signChallenge($privateKey, $challenge);

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
    }

    public function test_cannot_join_with_wrong_signature(): void
    {
        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $this->fetchChallenge($session);

        $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
            'signature' => base64_encode(random_bytes(64)),
        ]);

        $response->assertStatus(401)->assertJsonPath('message', 'Unauthorised');
    }

    public function test_join_returns_422_when_no_challenge_exists(): void
    {
        [$privateKey, , $session] = $this->generateKeyPairAndSession();

        $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
            'signature' => base64_encode(random_bytes(64)),
        ]);

        $response->assertStatus(422)->assertJsonPath('message', fn ($v) => str_contains($v, 'Challenge expired'));
    }

    public function test_join_prevents_challenge_replay(): void
    {
        Http::fake(['*' => Http::response([['urls' => 'stun:stun.example.com']], 200)]);

        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $challenge = $this->fetchChallenge($session);
        $signature = $this->signChallenge($privateKey, $challenge);

        // First join succeeds
        $this->postJson("/call-sessions/{$session->hash_id}/join", ['signature' => $signature])
            ->assertOk();

        // Second join with same signature fails — challenge was consumed
        $this->postJson("/call-sessions/{$session->hash_id}/join", ['signature' => $signature])
            ->assertStatus(422);
    }

    public function test_join_returns_ice_servers_from_turn_service(): void
    {
        $fakeServers = [['urls' => 'turn:relay.example.com', 'username' => 'u', 'credential' => 'p']];
        Http::fake(['*' => Http::response($fakeServers, 200)]);

        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $challenge = $this->fetchChallenge($session);

        $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
            'signature' => $this->signChallenge($privateKey, $challenge),
        ]);

        $response->assertOk()
            ->assertJsonPath('turn_available', true)
            ->assertJsonCount(1, 'ice_servers');
    }

    public function test_join_returns_empty_ice_servers_when_turn_service_fails(): void
    {
        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $challenge = $this->fetchChallenge($session);

        // Fake TURN provider failure AFTER the challenge request (avoids intercepting Cloudflare proxy lookup)
        Http::fake(['*.metered.ca/*' => Http::response('error', 500)]);

        $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
            'signature' => $this->signChallenge($privateKey, $challenge),
        ]);

        $response->assertOk()
            ->assertJsonPath('turn_available', false)
            ->assertJsonPath('ice_servers', []);
    }

    public function test_join_creates_participant_record(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $challenge = $this->fetchChallenge($session);

        $this->postJson("/call-sessions/{$session->hash_id}/join", [
            'signature' => $this->signChallenge($privateKey, $challenge),
        ])->assertOk();

        $this->assertCount(1, $session->fresh()->participants);
    }

    public function test_join_returns_404_for_unknown_hash_id(): void
    {
        $response = $this->postJson('/call-sessions/unknownhash/join', [
            'signature' => base64_encode(random_bytes(64)),
        ]);

        $response->assertNotFound();
    }

    public function test_join_requires_signature_field(): void
    {
        $session = CallSession::factory()->create();

        $response = $this->postJson("/call-sessions/{$session->hash_id}/join", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['signature']);
    }

    public function test_join_response_includes_hash_id_not_integer_pk(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        [$privateKey, , $session] = $this->generateKeyPairAndSession();
        $challenge = $this->fetchChallenge($session);

        $response = $this->postJson("/call-sessions/{$session->hash_id}/join", [
            'signature' => $this->signChallenge($privateKey, $challenge),
        ])->assertOk();

        $bridgeNumber = $response->json('session.bridge_number');
        $this->assertNotEquals((string) $session->id, $bridgeNumber);
        $this->assertEquals($session->hash_id, $bridgeNumber);
    }
}
