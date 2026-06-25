<?php

use App\Jobs\ClearExpiredCallSessions;
use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\CallSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pruneAfter = config('secrets.prune_after');
});

test('deletes sessions past prune window', function () {
    $old = CallSession::factory()->create([
        'ends_at' => now()->subDays($this->pruneAfter + 1),
    ]);

    (new ClearExpiredCallSessions)->handle();

    $this->assertDatabaseMissing('call_sessions', ['id' => $old->id]);
});

test('preserves sessions within prune window', function () {
    $recent = CallSession::factory()->create([
        'ends_at' => now()->subDays($this->pruneAfter - 1),
    ]);

    (new ClearExpiredCallSessions)->handle();

    $this->assertDatabaseHas('call_sessions', ['id' => $recent->id]);
});

test('preserves active sessions', function () {
    $active = CallSession::factory()->create();

    (new ClearExpiredCallSessions)->handle();

    $this->assertDatabaseHas('call_sessions', ['id' => $active->id]);
});

test('cascade deletes participants when session is pruned', function () {
    $session = CallSession::factory()->create([
        'ends_at' => now()->subDays($this->pruneAfter + 1),
    ]);
    $participant = CallParticipant::factory()->for($session, 'session')->create();

    (new ClearExpiredCallSessions)->handle();

    $this->assertDatabaseMissing('call_participants', ['id' => $participant->id]);
});

test('cascade deletes signals when session is pruned', function () {
    $session = CallSession::factory()->create([
        'ends_at' => now()->subDays($this->pruneAfter + 1),
    ]);
    $from = CallParticipant::factory()->for($session, 'session')->create();
    $to = CallParticipant::factory()->for($session, 'session')->create();

    $signal = CallSignal::create([
        'call_session_id' => $session->id,
        'from_participant_id' => $from->id,
        'to_participant_id' => $to->id,
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ]);

    (new ClearExpiredCallSessions)->handle();

    $this->assertDatabaseMissing('call_signals', ['id' => $signal->id]);
});
