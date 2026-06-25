<?php

use App\Models\PipeDevice;
use App\Models\PipeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sessionId = 'abcdef1234567890abcdef1234567890';
    $this->user = User::factory()->create();
});

test('guest cannot create pipe session', function () {
    $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
    ])->assertStatus(401);
});

test('authenticated user can create session', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('pipe_sessions', [
        'session_id' => $this->sessionId,
        'user_id' => $this->user->id,
    ]);
});

test('custom expires in is honoured', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
        'expires_in' => 120,
    ]);

    $response->assertStatus(201);
    $session = PipeSession::where('session_id', $this->sessionId)->first();
    expect($session)->not->toBeNull();
    expect(now()->diffInSeconds($session->expires_at))->toEqualWithDelta(120, 5);
});

test('expires in below minimum returns 422', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
        'expires_in' => 59,
    ])->assertStatus(422);
});

test('expires in above maximum returns 422', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
        'expires_in' => 3601,
    ])->assertStatus(422);
});

test('omitting expires in uses config default', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
    ]);

    $response->assertStatus(201);
    $session = PipeSession::where('session_id', $this->sessionId)->first();
    expect($session)->not->toBeNull();
    $expected = config('pipe.session_ttl_seconds');
    expect(now()->diffInSeconds($session->expires_at))->toEqualWithDelta($expected, 5);
});

test('duplicate session id returns 422', function () {
    Sanctum::actingAs($this->user);
    PipeSession::factory()->create(['session_id' => $this->sessionId]);

    $this->postJson('/api/v1/pipe', [
        'session_id' => $this->sessionId,
        'transfer_mode' => 'relay',
    ])->assertStatus(422);
});

test('invalid session id format returns 422', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/pipe', [
        'session_id' => 'not-hex!',
        'transfer_mode' => 'relay',
    ])->assertStatus(422);
});

test('can get session status', function () {
    Sanctum::actingAs($this->user);

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson("/api/v1/pipe/{$this->sessionId}")
        ->assertStatus(200)
        ->assertJsonPath('session_id', $this->sessionId)
        ->assertJsonPath('is_complete', false);
});

test('expired session returns 404', function () {
    Sanctum::actingAs($this->user);

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->subMinute(),
    ]);

    $this->getJson("/api/v1/pipe/{$this->sessionId}")->assertStatus(404);
});

test('unknown session returns 404', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/pipe/0000000000000000000000000000000a')->assertStatus(404);
});

test('can prepare upload', function () {
    Sanctum::actingAs($this->user);
    Storage::fake();

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/prepare-upload");

    $response->assertStatus(200)
        ->assertJsonStructure(['upload_type', 'upload_url', 'upload_headers']);

    expect(['s3_direct', 'server'])->toContain($response->json('upload_type'));
});

test('prepare upload on expired session returns 404', function () {
    Sanctum::actingAs($this->user);
    Storage::fake();

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson("/api/v1/pipe/{$this->sessionId}/prepare-upload")->assertStatus(404);
});

test('prepare upload on complete session returns 422', function () {
    Sanctum::actingAs($this->user);
    Storage::fake();
    Storage::put("pipe-payloads/{$this->sessionId}.bin", 'data');

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->addMinutes(10),
        'is_complete' => true,
        'storage_path' => "pipe-payloads/{$this->sessionId}.bin",
    ]);

    $this->postJson("/api/v1/pipe/{$this->sessionId}/prepare-upload")->assertStatus(422);
});

test('can upload payload via server', function () {
    Sanctum::actingAs($this->user);
    Storage::fake();

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->call(
        'PUT',
        "/api/v1/pipe/{$this->sessionId}/payload",
        [], [], [],
        ['HTTP_ACCEPT' => 'application/json'],
        'binary-payload-data'
    )
        ->assertStatus(200)
        ->assertJsonPath('status', 'ok');

    Storage::assertExists("pipe-payloads/{$this->sessionId}.bin");
});

test('can complete session after upload', function () {
    Sanctum::actingAs($this->user);
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
});

test('complete without upload returns 422', function () {
    Sanctum::actingAs($this->user);
    Storage::fake();

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson("/api/v1/pipe/{$this->sessionId}/complete")->assertStatus(422);
});

test('can download payload', function () {
    Sanctum::actingAs($this->user);
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
    expect([200, 302])->toContain($response->status());
});

test('download before complete returns 202', function () {
    Sanctum::actingAs($this->user);
    Storage::fake();

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'expires_at' => now()->addMinutes(10),
        'is_complete' => false,
    ]);

    $this->getJson("/api/v1/pipe/{$this->sessionId}/download")->assertStatus(202);
});

test('can burn session', function () {
    Sanctum::actingAs($this->user);
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
});

test('burning unknown session returns 204', function () {
    Sanctum::actingAs($this->user);

    $this->deleteJson('/api/v1/pipe/0000000000000000000000000000000b')->assertStatus(204);
});

test('authenticated user can create session with device fields', function () {
    Sanctum::actingAs($this->user);

    $senderDevice = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);
    $receiverDevice = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);

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
});

test('pending sessions returns session for receiver device', function () {
    Sanctum::actingAs($this->user);

    $senderDevice = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);
    $receiverDevice = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'user_id' => $this->user->id,
        'sender_device_id' => $senderDevice->id,
        'receiver_device_id' => $receiverDevice->id,
        'encrypted_transfer_key' => base64_encode('fake-key'),
        'is_complete' => false,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson("/api/v1/pipe/sessions/pending?device_id={$receiverDevice->device_id}")
        ->assertStatus(200)
        ->assertJsonPath('session_id', $this->sessionId)
        ->assertJsonStructure(['session_id', 'encrypted_transfer_key', 'sender_device_id', 'sender_public_key']);
});

test('pending sessions returns 204 when none pending', function () {
    Sanctum::actingAs($this->user);

    $device = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);

    $this->getJson("/api/v1/pipe/sessions/pending?device_id={$device->device_id}")
        ->assertStatus(204);
});

test('pending sessions returns complete sessions', function () {
    // When the sender uploads fast, the session may already be complete by the time
    // the receiver polls. The endpoint must return it so the receiver can download.
    Sanctum::actingAs($this->user);

    $senderDevice = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);
    $receiverDevice = PipeDevice::factory()->create(['user_id' => $this->user->id, 'expires_at' => now()->addYear()]);

    PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'user_id' => $this->user->id,
        'sender_device_id' => $senderDevice->id,
        'receiver_device_id' => $receiverDevice->id,
        'encrypted_transfer_key' => base64_encode('fake-key'),
        'is_complete' => true,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson("/api/v1/pipe/sessions/pending?device_id={$receiverDevice->device_id}")
        ->assertStatus(200)
        ->assertJsonPath('session_id', $this->sessionId);
});

test('pending sessions requires authentication', function () {
    $this->getJson('/api/v1/pipe/sessions/pending?device_id=DEVABCD')
        ->assertStatus(401);
});

test('pending sessions cannot access another users device', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($this->user);

    $otherDevice = PipeDevice::factory()->create(['user_id' => $otherUser->id, 'expires_at' => now()->addYear()]);

    $this->getJson("/api/v1/pipe/sessions/pending?device_id={$otherDevice->device_id}")
        ->assertStatus(404);
});
