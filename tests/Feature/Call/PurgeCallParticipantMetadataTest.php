<?php

namespace Tests\Feature\Call;

use App\Jobs\PurgeCallParticipantMetadata;
use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeCallParticipantMetadataTest extends TestCase
{
    use RefreshDatabase;

    private function pruneAfter(): int
    {
        return config('secrets.prune_after', 30);
    }

    public function test_purge_job_deletes_participants_from_sessions_past_retention_window(): void
    {
        $session = CallSession::factory()->create([
            'ends_at' => now()->subDays($this->pruneAfter() + 1),
        ]);
        $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

        dispatch(new PurgeCallParticipantMetadata);

        $this->assertDatabaseMissing('call_participants', ['id' => $participant->id]);
    }

    public function test_purge_job_retains_participants_from_recently_ended_sessions(): void
    {
        $session = CallSession::factory()->create([
            'ends_at' => now()->subDays($this->pruneAfter() - 1),
        ]);
        $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

        dispatch(new PurgeCallParticipantMetadata);

        $this->assertDatabaseHas('call_participants', ['id' => $participant->id]);
    }

    public function test_purge_job_retains_participants_from_active_sessions(): void
    {
        $session = CallSession::factory()->create();
        $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

        dispatch(new PurgeCallParticipantMetadata);

        $this->assertDatabaseHas('call_participants', ['id' => $participant->id]);
    }

    public function test_purge_job_retains_call_session_record_after_purging_participants(): void
    {
        $session = CallSession::factory()->create([
            'ends_at' => now()->subDays($this->pruneAfter() + 1),
        ]);
        CallParticipant::factory()->create(['call_session_id' => $session->id]);

        dispatch(new PurgeCallParticipantMetadata);

        $this->assertDatabaseHas('call_sessions', ['id' => $session->id]);
    }
}
