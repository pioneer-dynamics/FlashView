<?php

namespace Tests\Feature\Call;

use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\CallSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallSignalTest extends TestCase
{
    use RefreshDatabase;

    private function activeSession(): CallSession
    {
        return CallSession::factory()->create();
    }

    private function inactiveSession(): CallSession
    {
        return CallSession::factory()->inactive()->create();
    }

    private function participantIn(CallSession $session): CallParticipant
    {
        return CallParticipant::factory()->for($session, 'session')->create();
    }

    // ─── participants ──────────────────────────────────────────────────────────

    public function test_participants_returns_active_participants(): void
    {
        $session = $this->activeSession();
        $participant = $this->participantIn($session);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

        $response->assertOk()
            ->assertJsonCount(1, 'participants')
            ->assertJsonPath('participants.0.id', $participant->id);
    }

    public function test_participants_does_not_include_ip_address(): void
    {
        $session = $this->activeSession();
        $this->participantIn($session);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

        $response->assertOk();
        $this->assertArrayNotHasKey('ip_address', $response->json('participants.0'));
    }

    public function test_participants_excludes_participants_who_have_left(): void
    {
        $session = $this->activeSession();
        CallParticipant::factory()->for($session, 'session')->create(['left_at' => now()]);
        $active = $this->participantIn($session);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

        $response->assertOk()
            ->assertJsonCount(1, 'participants')
            ->assertJsonPath('participants.0.id', $active->id);
    }

    public function test_participants_includes_expected_fields(): void
    {
        $session = $this->activeSession();
        $this->participantIn($session);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

        $response->assertOk()
            ->assertJsonStructure(['participants' => [['id', 'joined_at', 'public_key']]]);
    }

    public function test_participants_returns_404_for_inactive_session(): void
    {
        $session = $this->inactiveSession();

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

        $response->assertNotFound();
    }

    // ─── store (POST signal) ───────────────────────────────────────────────────

    public function test_store_creates_signal_and_returns_201(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response->assertCreated()->assertJsonStructure(['signal_id']);
        $this->assertDatabaseHas('call_signals', [
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'offer',
        ]);
    }

    public function test_store_returns_422_when_from_participant_not_in_session(): void
    {
        $session = $this->activeSession();
        $to = $this->participantIn($session);
        $strangerUuid = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $strangerUuid,
            'to_participant_id' => $to->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response->assertUnprocessable()->assertJsonPath('message', 'Invalid participant ID for this session.');
    }

    public function test_store_returns_422_when_to_participant_not_in_session(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $strangerUuid = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $from->id,
            'to_participant_id' => $strangerUuid,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response->assertUnprocessable()->assertJsonPath('message', 'Invalid participant ID for this session.');
    }

    public function test_store_returns_422_when_self_signal(): void
    {
        $session = $this->activeSession();
        $participant = $this->participantIn($session);

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $participant->id,
            'to_participant_id' => $participant->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['to_participant_id']);
    }

    public function test_store_returns_422_for_invalid_signal_type(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'unknown-type',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_store_returns_422_when_payload_is_missing(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'offer',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['payload']);
    }

    public function test_store_returns_404_for_inactive_session(): void
    {
        $session = $this->inactiveSession();

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => Str::uuid()->toString(),
            'to_participant_id' => Str::uuid()->toString(),
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response->assertNotFound();
    }

    public function test_store_accepts_key_exchange_signal_type(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'key-exchange',
            'payload' => ['wrapped_key' => base64_encode(random_bytes(32))],
        ]);

        $response->assertCreated();
    }

    // ─── index (GET signal) ────────────────────────────────────────────────────

    public function test_index_returns_signals_addressed_to_participant(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        CallSignal::create([
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$to->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'signals')
            ->assertJsonPath('signals.0.from_participant_id', $from->id)
            ->assertJsonPath('signals.0.type', 'offer');
    }

    public function test_index_does_not_return_signals_addressed_to_other_participants(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);
        $other = $this->participantIn($session);

        CallSignal::create([
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $other->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$to->id}");

        $response->assertOk()->assertJsonCount(0, 'signals');
    }

    public function test_index_respects_after_cursor(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        $signal1 = CallSignal::create([
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        CallSignal::create([
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'answer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$to->id}&after={$signal1->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'signals')
            ->assertJsonPath('signals.0.type', 'answer');
    }

    public function test_index_returns_empty_array_when_no_signals(): void
    {
        $session = $this->activeSession();
        $participant = $this->participantIn($session);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$participant->id}");

        $response->assertOk()->assertJsonCount(0, 'signals');
    }

    public function test_index_returns_422_when_participant_not_in_session(): void
    {
        $session = $this->activeSession();
        $strangerUuid = Str::uuid()->toString();

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$strangerUuid}");

        $response->assertUnprocessable()->assertJsonPath('message', 'Invalid participant ID for this session.');
    }

    public function test_index_returns_404_for_inactive_session(): void
    {
        $session = $this->inactiveSession();

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id=".Str::uuid()->toString());

        $response->assertNotFound();
    }

    public function test_index_includes_from_participant_id_in_response(): void
    {
        $session = $this->activeSession();
        $from = $this->participantIn($session);
        $to = $this->participantIn($session);

        CallSignal::create([
            'call_session_id' => $session->id,
            'from_participant_id' => $from->id,
            'to_participant_id' => $to->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ]);

        $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$to->id}");

        $response->assertOk()
            ->assertJsonStructure(['signals' => [['id', 'from_participant_id', 'type', 'payload']]]);
    }

    // ─── join via API route ────────────────────────────────────────────────────

    public function test_join_via_api_route_returns_correct_response_structure(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $privateKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);

        $session = CallSession::factory()->create([
            'public_key' => base64_encode($publicKey),
        ]);

        $challenge = bin2hex(random_bytes(32));
        cache()->put("call-challenge:{$session->id}", $challenge, 60);

        $signature = base64_encode(sodium_crypto_sign_detached(hex2bin($challenge), $privateKey));

        $response = $this->postJson("/api/v1/calls/{$session->hash_id}/join", [
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
}
