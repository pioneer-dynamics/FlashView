<?php

namespace Tests\Feature;

use App\Models\PipeChunk;
use App\Models\PipeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PipeSessionTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionId = 'abcdef1234567890abcdef1234567890'; // 32-char hex

    // ─── Create session ───────────────────────────────────────────────────────

    public function test_guest_can_create_pipe_session(): void
    {
        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('session_id', $this->sessionId)
            ->assertJsonStructure(['session_id', 'expires_at', 'transfer_mode']);

        $this->assertDatabaseHas('pipe_sessions', ['session_id' => $this->sessionId]);
    }

    public function test_authenticated_user_can_create_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('pipe_sessions', [
            'session_id' => $this->sessionId,
            'user_id' => $user->id,
        ]);
    }

    public function test_custom_expires_in_is_honoured(): void
    {
        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
            'expires_in' => 120,
        ]);

        $response->assertStatus(201);
        $session = PipeSession::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($session);
        $this->assertEqualsWithDelta(120, now()->diffInSeconds($session->expires_at), 5);
    }

    public function test_expires_in_below_minimum_returns_422(): void
    {
        $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
            'expires_in' => 59,
        ])->assertStatus(422);
    }

    public function test_expires_in_above_maximum_returns_422(): void
    {
        $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
            'expires_in' => 3601,
        ])->assertStatus(422);
    }

    public function test_omitting_expires_in_uses_config_default(): void
    {
        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
        ]);

        $response->assertStatus(201);
        $session = PipeSession::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($session);
        $expected = config('pipe.session_ttl_seconds');
        $this->assertEqualsWithDelta($expected, now()->diffInSeconds($session->expires_at), 5);
    }

    public function test_duplicate_session_id_returns_422(): void
    {
        PipeSession::factory()->create(['session_id' => $this->sessionId]);

        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_session_id_format_returns_422(): void
    {
        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => 'not-hex!',
            'transfer_mode' => 'relay',
        ]);

        $response->assertStatus(422);
    }

    // ─── Session status ───────────────────────────────────────────────────────

    public function test_can_get_session_status(): void
    {
        $session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->getJson("/api/v1/pipe/{$this->sessionId}");

        $response->assertStatus(200)
            ->assertJsonPath('session_id', $this->sessionId)
            ->assertJsonPath('is_complete', false)
            ->assertJsonPath('chunk_count', 0);
    }

    public function test_expired_session_returns_404(): void
    {
        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson("/api/v1/pipe/{$this->sessionId}")->assertStatus(404);
    }

    public function test_unknown_session_returns_404(): void
    {
        $this->getJson('/api/v1/pipe/0000000000000000000000000000000a')->assertStatus(404);
    }

    // ─── Upload chunk ─────────────────────────────────────────────────────────

    public function test_can_upload_chunk(): void
    {
        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/chunk", [
            'chunk_index' => 0,
            'payload' => base64_encode('encrypted-data-here'),
        ]);

        $response->assertStatus(201)->assertJsonPath('chunk_index', 0);
        $this->assertDatabaseCount('pipe_chunks', 1);
    }

    public function test_duplicate_chunk_index_returns_409(): void
    {
        $session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        PipeChunk::factory()->create([
            'pipe_session_id' => $session->id,
            'chunk_index' => 0,
        ]);

        $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/chunk", [
            'chunk_index' => 0,
            'payload' => base64_encode('other-data'),
        ]);

        $response->assertStatus(409);
    }

    public function test_chunk_upload_to_expired_session_returns_404(): void
    {
        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/v1/pipe/{$this->sessionId}/chunk", [
            'chunk_index' => 0,
            'payload' => base64_encode('data'),
        ])->assertStatus(404);
    }

    // ─── Download chunk ───────────────────────────────────────────────────────

    public function test_can_download_existing_chunk(): void
    {
        $session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        PipeChunk::factory()->create([
            'pipe_session_id' => $session->id,
            'chunk_index' => 0,
            'payload' => base64_encode('secret-data'),
        ]);

        $response = $this->getJson("/api/v1/pipe/{$this->sessionId}/chunk/0");

        $response->assertStatus(200)->assertJsonStructure(['payload']);
    }

    public function test_downloading_missing_chunk_returns_202(): void
    {
        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->getJson("/api/v1/pipe/{$this->sessionId}/chunk/0")
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');
    }

    // ─── Complete session ─────────────────────────────────────────────────────

    public function test_can_complete_session_with_correct_chunk_count(): void
    {
        $session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        PipeChunk::factory()->create(['pipe_session_id' => $session->id, 'chunk_index' => 0]);
        PipeChunk::factory()->create(['pipe_session_id' => $session->id, 'chunk_index' => 1]);

        $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/complete", [
            'total_chunks' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('is_complete', true)
            ->assertJsonPath('total_chunks', 2);

        $this->assertDatabaseHas('pipe_sessions', [
            'session_id' => $this->sessionId,
            'is_complete' => true,
        ]);
    }

    public function test_completing_with_wrong_chunk_count_returns_422(): void
    {
        $session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        PipeChunk::factory()->create(['pipe_session_id' => $session->id, 'chunk_index' => 0]);

        $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/complete", [
            'total_chunks' => 3,
        ]);

        $response->assertStatus(422);
    }

    // ─── Burn session ─────────────────────────────────────────────────────────

    public function test_can_burn_session(): void
    {
        $session = PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        PipeChunk::factory()->create(['pipe_session_id' => $session->id, 'chunk_index' => 0]);

        $this->deleteJson("/api/v1/pipe/{$this->sessionId}")->assertStatus(204);

        $this->assertDatabaseMissing('pipe_sessions', ['session_id' => $this->sessionId]);
        $this->assertDatabaseCount('pipe_chunks', 0);
    }

    public function test_burning_unknown_session_returns_204(): void
    {
        $this->deleteJson('/api/v1/pipe/0000000000000000000000000000000b')->assertStatus(204);
    }
}
