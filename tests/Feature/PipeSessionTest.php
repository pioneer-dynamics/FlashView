<?php

namespace Tests\Feature;

use App\Models\PipeDevice;
use App\Models\PipeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->getJson("/api/v1/pipe/{$this->sessionId}");

        $response->assertStatus(200)
            ->assertJsonPath('session_id', $this->sessionId)
            ->assertJsonPath('is_complete', false);
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

    // ─── Prepare upload ───────────────────────────────────────────────────────

    public function test_can_prepare_upload(): void
    {
        Storage::fake();

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/prepare-upload");

        $response->assertStatus(200)
            ->assertJsonStructure(['upload_type', 'upload_url', 'upload_headers']);

        $this->assertContains($response->json('upload_type'), ['s3_direct', 'server']);
    }

    public function test_prepare_upload_on_expired_session_returns_404(): void
    {
        Storage::fake();

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/v1/pipe/{$this->sessionId}/prepare-upload")->assertStatus(404);
    }

    public function test_prepare_upload_on_complete_session_returns_422(): void
    {
        Storage::fake();
        Storage::put("pipe-payloads/{$this->sessionId}.bin", 'data');

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
            'is_complete' => true,
            'storage_path' => "pipe-payloads/{$this->sessionId}.bin",
        ]);

        $this->postJson("/api/v1/pipe/{$this->sessionId}/prepare-upload")->assertStatus(422);
    }

    // ─── Server-side upload ───────────────────────────────────────────────────

    public function test_can_upload_payload_via_server(): void
    {
        Storage::fake();

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->call('PUT', "/api/v1/pipe/{$this->sessionId}/payload", [], [], [], [], 'binary-payload-data')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        Storage::assertExists("pipe-payloads/{$this->sessionId}.bin");
    }

    // ─── Complete session ─────────────────────────────────────────────────────

    public function test_can_complete_session_after_upload(): void
    {
        Storage::fake();
        Storage::put("pipe-payloads/{$this->sessionId}.bin", 'encrypted-data');

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson("/api/v1/pipe/{$this->sessionId}/complete")
            ->assertStatus(200)
            ->assertJsonPath('is_complete', true);

        $this->assertDatabaseHas('pipe_sessions', [
            'session_id' => $this->sessionId,
            'is_complete' => true,
            'storage_path' => "pipe-payloads/{$this->sessionId}.bin",
        ]);
    }

    public function test_complete_without_upload_returns_422(): void
    {
        Storage::fake();

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson("/api/v1/pipe/{$this->sessionId}/complete")->assertStatus(422);
    }

    // ─── Download payload ─────────────────────────────────────────────────────

    public function test_can_download_payload(): void
    {
        Storage::fake();
        Storage::put("pipe-payloads/{$this->sessionId}.bin", 'encrypted-data');

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
            'is_complete' => true,
            'storage_path' => "pipe-payloads/{$this->sessionId}.bin",
        ]);

        // S3: redirects (302) to presigned URL; local fallback: streams directly (200)
        $response = $this->getJson("/api/v1/pipe/{$this->sessionId}/download");
        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_download_before_complete_returns_202(): void
    {
        Storage::fake();

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
            'is_complete' => false,
        ]);

        $this->getJson("/api/v1/pipe/{$this->sessionId}/download")->assertStatus(202);
    }

    // ─── Burn session ─────────────────────────────────────────────────────────

    public function test_can_burn_session(): void
    {
        Storage::fake();
        Storage::put("pipe-payloads/{$this->sessionId}.bin", 'encrypted-data');

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'expires_at' => now()->addMinutes(10),
            'is_complete' => true,
            'storage_path' => "pipe-payloads/{$this->sessionId}.bin",
        ]);

        $this->deleteJson("/api/v1/pipe/{$this->sessionId}")->assertStatus(204);

        $this->assertDatabaseMissing('pipe_sessions', ['session_id' => $this->sessionId]);
        Storage::assertMissing("pipe-payloads/{$this->sessionId}.bin");
    }

    public function test_burning_unknown_session_returns_204(): void
    {
        $this->deleteJson('/api/v1/pipe/0000000000000000000000000000000b')->assertStatus(204);
    }

    // ─── Task 17: per-transfer key fields ─────────────────────────────────────

    public function test_authenticated_user_can_create_session_with_device_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $senderDevice = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);
        $receiverDevice = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);

        $response = $this->postJson('/api/v1/pipe', [
            'session_id' => $this->sessionId,
            'transfer_mode' => 'relay',
            'sender_device_id' => $senderDevice->device_id,
            'receiver_device_id' => $receiverDevice->device_id,
            'encrypted_transfer_key' => base64_encode('fake-encrypted-key'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('pipe_sessions', [
            'session_id' => $this->sessionId,
            'sender_device_id' => $senderDevice->id,
            'receiver_device_id' => $receiverDevice->id,
        ]);
    }

    public function test_pending_sessions_returns_session_for_receiver_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $senderDevice = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);
        $receiverDevice = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'user_id' => $user->id,
            'sender_device_id' => $senderDevice->id,
            'receiver_device_id' => $receiverDevice->id,
            'encrypted_transfer_key' => base64_encode('fake-key'),
            'is_complete' => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->getJson("/api/v1/pipe/sessions/pending?device_id={$receiverDevice->device_id}");

        $response->assertStatus(200)
            ->assertJsonPath('session_id', $this->sessionId)
            ->assertJsonStructure(['session_id', 'encrypted_transfer_key', 'sender_device_id', 'sender_public_key']);
    }

    public function test_pending_sessions_returns_204_when_none_pending(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);

        $response = $this->getJson("/api/v1/pipe/sessions/pending?device_id={$device->device_id}");

        $response->assertStatus(204);
    }

    public function test_pending_sessions_returns_complete_sessions(): void
    {
        // When the sender uploads fast, the session may already be complete by the time
        // the receiver polls. The endpoint must return it so the receiver can download.
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $senderDevice = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);
        $receiverDevice = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);

        PipeSession::factory()->create([
            'session_id' => $this->sessionId,
            'user_id' => $user->id,
            'sender_device_id' => $senderDevice->id,
            'receiver_device_id' => $receiverDevice->id,
            'encrypted_transfer_key' => base64_encode('fake-key'),
            'is_complete' => true,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->getJson("/api/v1/pipe/sessions/pending?device_id={$receiverDevice->device_id}")
            ->assertStatus(200)
            ->assertJsonPath('session_id', $this->sessionId);
    }

    public function test_pending_sessions_requires_authentication(): void
    {
        $this->getJson('/api/v1/pipe/sessions/pending?device_id=DEVABCD')
            ->assertStatus(401);
    }

    public function test_pending_sessions_cannot_access_another_users_device(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $otherDevice = PipeDevice::factory()->create(['user_id' => $otherUser->id, 'expires_at' => now()->addYear()]);

        $this->getJson("/api/v1/pipe/sessions/pending?device_id={$otherDevice->device_id}")
            ->assertStatus(404);
    }
}
