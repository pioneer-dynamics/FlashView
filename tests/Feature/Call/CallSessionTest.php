<?php

namespace Tests\Feature\Call;

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_session_can_be_created_with_factory(): void
    {
        $session = CallSession::factory()->create();

        $this->assertDatabaseHas('call_sessions', ['id' => $session->id]);
        $this->assertNotEmpty($session->public_key);
        $this->assertNotEmpty($session->key_salt);
    }

    public function test_hash_id_is_computed_from_integer_pk(): void
    {
        $session = CallSession::factory()->create();

        $this->assertNotEmpty($session->hash_id);
        $this->assertIsString($session->hash_id);
        $this->assertEquals(10, strlen($session->hash_id));
    }

    public function test_active_scope_returns_sessions_within_time_window(): void
    {
        $active = CallSession::factory()->create([
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(50),
        ]);

        $this->assertDatabaseHas('call_sessions', ['id' => $active->id]);
        $this->assertCount(1, CallSession::active()->get());
    }

    public function test_active_scope_excludes_sessions_outside_time_window(): void
    {
        CallSession::factory()->inactive()->create();
        CallSession::factory()->notYetStarted()->create();

        $this->assertCount(0, CallSession::active()->get());
    }

    public function test_joinable_scope_delegates_to_active(): void
    {
        $active = CallSession::factory()->create();
        CallSession::factory()->inactive()->create();

        $joinable = CallSession::joinable()->get();

        $this->assertCount(1, $joinable);
        $this->assertTrue($joinable->first()->is($active));
    }

    public function test_is_full_returns_true_when_participants_at_max(): void
    {
        $session = CallSession::factory()->create(['max_participants' => 2]);
        CallParticipant::factory()->count(2)->create(['call_session_id' => $session->id]);

        $this->assertTrue($session->isFull());
    }

    public function test_is_full_returns_false_when_below_max(): void
    {
        $session = CallSession::factory()->create(['max_participants' => 2]);
        CallParticipant::factory()->create(['call_session_id' => $session->id]);

        $this->assertFalse($session->isFull());
    }

    public function test_is_active_returns_true_for_active_session(): void
    {
        $session = CallSession::factory()->create();

        $this->assertTrue($session->isActive());
    }

    public function test_is_active_returns_false_for_inactive_session(): void
    {
        $session = CallSession::factory()->inactive()->create();

        $this->assertFalse($session->isActive());
    }
}
