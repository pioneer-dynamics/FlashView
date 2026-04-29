<?php

namespace Tests\Feature;

use App\Models\PipeSession;
use App\Models\PipeSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipeSignalTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionId = 'abcdef1234567890abcdef1234567891';

    private PipeSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function test_can_store_signal(): void
    {
        $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/signal", [
            'role' => 'sender',
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0...'],
        ]);

        $response->assertStatus(201)->assertJsonStructure(['signal_id']);
        $this->assertDatabaseCount('pipe_signals', 1);
    }

    public function test_invalid_role_returns_422(): void
    {
        $this->postJson("/api/v1/pipe/{$this->sessionId}/signal", [
            'role' => 'unknown',
            'type' => 'offer',
            'payload' => [],
        ])->assertStatus(422);
    }

    public function test_invalid_type_returns_422(): void
    {
        $this->postJson("/api/v1/pipe/{$this->sessionId}/signal", [
            'role' => 'sender',
            'type' => 'invalid-type',
            'payload' => [],
        ])->assertStatus(422);
    }

    public function test_can_poll_signals_after_id(): void
    {
        PipeSignal::create([
            'pipe_session_id' => $this->session->id,
            'role' => 'sender',
            'type' => 'offer',
            'payload' => ['sdp' => 'old'],
        ]);

        $second = PipeSignal::create([
            'pipe_session_id' => $this->session->id,
            'role' => 'sender',
            'type' => 'ice-candidate',
            'payload' => ['candidate' => 'ice'],
        ]);

        $response = $this->getJson("/api/v1/pipe/{$this->sessionId}/signal?role=sender&after=".($second->id - 1));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'signals')
            ->assertJsonPath('signals.0.type', 'ice-candidate');
    }

    public function test_polling_signals_requires_valid_role(): void
    {
        $this->getJson("/api/v1/pipe/{$this->sessionId}/signal?role=invalid")
            ->assertStatus(422);
    }

    public function test_signal_to_expired_session_returns_404(): void
    {
        $expiredSession = PipeSession::factory()->create([
            'session_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa0',
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/pipe/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa0/signal', [
            'role' => 'sender',
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
        ])->assertStatus(404);
    }
}
