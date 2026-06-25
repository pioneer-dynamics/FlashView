<?php

use App\Jobs\PurgeCallParticipantMetadata;
use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pruneAfter(): int
{
    return config('secrets.prune_after', 30);
}

test('purge job deletes participants from sessions past retention window', function () {
    $session = CallSession::factory()->create([
        'ends_at' => now()->subDays(pruneAfter() + 1),
    ]);
    $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

    dispatch(new PurgeCallParticipantMetadata);

    $this->assertDatabaseMissing('call_participants', ['id' => $participant->id]);
});

test('purge job retains participants from recently ended sessions', function () {
    $session = CallSession::factory()->create([
        'ends_at' => now()->subDays(pruneAfter() - 1),
    ]);
    $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

    dispatch(new PurgeCallParticipantMetadata);

    $this->assertDatabaseHas('call_participants', ['id' => $participant->id]);
});

test('purge job retains participants from active sessions', function () {
    $session = CallSession::factory()->create();
    $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

    dispatch(new PurgeCallParticipantMetadata);

    $this->assertDatabaseHas('call_participants', ['id' => $participant->id]);
});

test('purge job retains call session record after purging participants', function () {
    $session = CallSession::factory()->create([
        'ends_at' => now()->subDays(pruneAfter() + 1),
    ]);
    CallParticipant::factory()->create(['call_session_id' => $session->id]);

    dispatch(new PurgeCallParticipantMetadata);

    $this->assertDatabaseHas('call_sessions', ['id' => $session->id]);
});
