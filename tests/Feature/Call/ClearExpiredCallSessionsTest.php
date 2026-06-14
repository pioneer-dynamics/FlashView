<?php

namespace Tests\Feature\Call;

use App\Jobs\ClearExpiredCallSessions;
use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\CallSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearExpiredCallSessionsTest extends TestCase
{
    use RefreshDatabase;

    private int $pruneAfter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pruneAfter = config('secrets.prune_after');
    }

    public function test_deletes_sessions_past_prune_window(): void
    {
        $old = CallSession::factory()->create([
            'ends_at' => now()->subDays($this->pruneAfter + 1),
        ]);

        (new ClearExpiredCallSessions)->handle();

        $this->assertDatabaseMissing('call_sessions', ['id' => $old->id]);
    }

    public function test_preserves_sessions_within_prune_window(): void
    {
        $recent = CallSession::factory()->create([
            'ends_at' => now()->subDays($this->pruneAfter - 1),
        ]);

        (new ClearExpiredCallSessions)->handle();

        $this->assertDatabaseHas('call_sessions', ['id' => $recent->id]);
    }

    public function test_preserves_active_sessions(): void
    {
        $active = CallSession::factory()->create();

        (new ClearExpiredCallSessions)->handle();

        $this->assertDatabaseHas('call_sessions', ['id' => $active->id]);
    }

    public function test_cascade_deletes_participants_when_session_is_pruned(): void
    {
        $session = CallSession::factory()->create([
            'ends_at' => now()->subDays($this->pruneAfter + 1),
        ]);
        $participant = CallParticipant::factory()->for($session, 'session')->create();

        (new ClearExpiredCallSessions)->handle();

        $this->assertDatabaseMissing('call_participants', ['id' => $participant->id]);
    }

    public function test_cascade_deletes_signals_when_session_is_pruned(): void
    {
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
    }
}
