<?php

use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\CallSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function activeSession(): CallSession
{
    return CallSession::factory()->create();
}

function inactiveSession(): CallSession
{
    return CallSession::factory()->inactive()->create();
}

function participantIn(CallSession $session): CallParticipant
{
    return CallParticipant::factory()->for($session, 'session')->create();
}

test('leave marks participant as left', function () {
    $session = activeSession();
    $participant = participantIn($session);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/leave", [
        'participant_id' => $participant->id,
    ]);

    $response->assertOk();
    expect($participant->fresh()->left_at)->not->toBeNull();
});

test('leave returns 404 when participant not in session', function () {
    $session = activeSession();
    $otherSession = activeSession();
    $participant = participantIn($otherSession);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/leave", [
        'participant_id' => $participant->id,
    ]);

    $response->assertNotFound();
});

test('leave is idempotent when participant already left', function () {
    $session = activeSession();
    $participant = CallParticipant::factory()->for($session, 'session')->create(['left_at' => now()->subMinute()]);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/leave", [
        'participant_id' => $participant->id,
    ]);

    $response->assertOk();
});

test('leave returns 422 when participant id is missing', function () {
    $session = activeSession();

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/leave", []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['participant_id']);
});

test('participants returns active participants', function () {
    $session = activeSession();
    $participant = participantIn($session);

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

    $response->assertOk()
        ->assertJsonCount(1, 'participants')
        ->assertJsonPath('participants.0.id', $participant->id);
});

test('participants does not include ip address', function () {
    $session = activeSession();
    participantIn($session);

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

    $response->assertOk();
    $this->assertArrayNotHasKey('ip_address', $response->json('participants.0'));
});

test('participants excludes participants who have left', function () {
    $session = activeSession();
    CallParticipant::factory()->for($session, 'session')->create(['left_at' => now()]);
    $active = participantIn($session);

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

    $response->assertOk()
        ->assertJsonCount(1, 'participants')
        ->assertJsonPath('participants.0.id', $active->id);
});

test('participants includes expected fields', function () {
    $session = activeSession();
    participantIn($session);

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

    $response->assertOk()
        ->assertJsonStructure(['participants' => [['id', 'joined_at', 'public_key']]]);
});

test('participants returns 404 for inactive session', function () {
    $session = inactiveSession();

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/participants");

    $response->assertNotFound();
});

test('store creates signal and returns 201', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

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
});

test('store returns 422 when from participant not in session', function () {
    $session = activeSession();
    $to = participantIn($session);
    $strangerUuid = Str::uuid()->toString();

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => $strangerUuid,
        'to_participant_id' => $to->id,
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ]);

    $response->assertUnprocessable()->assertJsonPath('message', 'Invalid participant ID for this session.');
});

test('store returns 422 when to participant not in session', function () {
    $session = activeSession();
    $from = participantIn($session);
    $strangerUuid = Str::uuid()->toString();

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => $from->id,
        'to_participant_id' => $strangerUuid,
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ]);

    $response->assertUnprocessable()->assertJsonPath('message', 'Invalid participant ID for this session.');
});

test('store returns 422 when self signal', function () {
    $session = activeSession();
    $participant = participantIn($session);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => $participant->id,
        'to_participant_id' => $participant->id,
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['to_participant_id']);
});

test('store returns 422 for invalid signal type', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => $from->id,
        'to_participant_id' => $to->id,
        'type' => 'unknown-type',
        'payload' => ['sdp' => 'v=0'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['type']);
});

test('store returns 422 when payload is missing', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => $from->id,
        'to_participant_id' => $to->id,
        'type' => 'offer',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['payload']);
});

test('store returns 404 for inactive session', function () {
    $session = inactiveSession();

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => Str::uuid()->toString(),
        'to_participant_id' => Str::uuid()->toString(),
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ]);

    $response->assertNotFound();
});

test('store accepts key exchange signal type', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

    $response = $this->postJson("/api/v1/calls/{$session->hash_id}/signal", [
        'from_participant_id' => $from->id,
        'to_participant_id' => $to->id,
        'type' => 'key-exchange',
        'payload' => ['wrapped_key' => base64_encode(random_bytes(32))],
    ]);

    $response->assertCreated();
});

test('index returns signals addressed to participant', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

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
});

test('index does not return signals addressed to other participants', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);
    $other = participantIn($session);

    CallSignal::create([
        'call_session_id' => $session->id,
        'from_participant_id' => $from->id,
        'to_participant_id' => $other->id,
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ]);

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$to->id}");

    $response->assertOk()->assertJsonCount(0, 'signals');
});

test('index respects after cursor', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

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
});

test('index returns empty array when no signals', function () {
    $session = activeSession();
    $participant = participantIn($session);

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$participant->id}");

    $response->assertOk()->assertJsonCount(0, 'signals');
});

test('index returns 422 when participant not in session', function () {
    $session = activeSession();
    $strangerUuid = Str::uuid()->toString();

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id={$strangerUuid}");

    $response->assertUnprocessable()->assertJsonPath('message', 'Invalid participant ID for this session.');
});

test('index returns 404 for inactive session', function () {
    $session = inactiveSession();

    $response = $this->getJson("/api/v1/calls/{$session->hash_id}/signal?participant_id=".Str::uuid()->toString());

    $response->assertNotFound();
});

test('index includes from participant id in response', function () {
    $session = activeSession();
    $from = participantIn($session);
    $to = participantIn($session);

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
});

test('join via api route returns correct response structure', function () {
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
});
