<?php

use App\Models\PipeDevice;
use App\Models\PipePairing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can register device', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/pipe/devices', [
        'public_key' => base64_encode(json_encode(['kty' => 'EC'])),
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['device_id', 'expires_at']);

    $this->assertDatabaseCount('pipe_devices', 1);
});

test('unauthenticated user cannot register device', function () {
    $this->postJson('/api/v1/pipe/devices', [
        'public_key' => base64_encode(json_encode(['kty' => 'EC'])),
    ])->assertStatus(401);
});

test('register device validates public key required', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/pipe/devices', [])->assertStatus(422);
});

test('can list waiting devices for own account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addMinutes(10)]);
    PipeDevice::factory()->create(['user_id' => $otherUser->id, 'expires_at' => now()->addMinutes(10)]);

    $response = $this->getJson('/api/v1/pipe/devices/waiting');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'devices');
});

test('expired devices not listed', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subMinute()]);

    $response = $this->getJson('/api/v1/pipe/devices/waiting');
    $response->assertStatus(200)->assertJsonCount(0, 'devices');
});

test('can destroy own device', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $device = PipeDevice::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/v1/pipe/devices/{$device->device_id}")->assertStatus(204);

    $this->assertDatabaseMissing('pipe_devices', ['id' => $device->id]);
});

test('cannot destroy other users device', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    $device = PipeDevice::factory()->create(['user_id' => $otherUser->id]);

    // Returns 204 (idempotent delete) but the device is NOT removed (user_id scope prevents it)
    $this->deleteJson("/api/v1/pipe/devices/{$device->device_id}")->assertStatus(204);

    $this->assertDatabaseHas('pipe_devices', ['id' => $device->id]);
});

test('can send seed to own device', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $senderDevice = PipeDevice::factory()->create([
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(10),
    ]);
    $receiverDevice = PipeDevice::factory()->create([
        'user_id' => $user->id,
        'device_id' => 'DEVAAAA',
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/pipe/pairings', [
        'receiver_device_id' => 'DEVAAAA',
        'encrypted_seed' => base64_encode(random_bytes(48)),
    ]);

    $response->assertStatus(201)->assertJsonStructure(['pairing_id']);
});

test('cannot send seed to another users device', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    PipeDevice::factory()->create([
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(10),
    ]);
    $otherDevice = PipeDevice::factory()->create([
        'user_id' => $otherUser->id,
        'device_id' => 'DEVBBBB',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/pipe/pairings', [
        'receiver_device_id' => 'DEVBBBB',
        'encrypted_seed' => base64_encode(random_bytes(48)),
    ])->assertStatus(404);
});

test('can poll pending seed for own device', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $receiverDevice = PipeDevice::factory()->create([
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(10),
    ]);
    $senderDevice = PipeDevice::factory()->create([
        'expires_at' => now()->addMinutes(10),
    ]);

    PipePairing::factory()->create([
        'sender_device_id' => $senderDevice->id,
        'receiver_device_id' => $receiverDevice->id,
    ]);

    $response = $this->getJson("/api/v1/pipe/pairings/pending?device_id={$receiverDevice->device_id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['pairing_id', 'sender_device_id', 'sender_public_key', 'encrypted_seed']);
});

test('pending seed returns 204 when no pairing', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $device = PipeDevice::factory()->create([
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson("/api/v1/pipe/pairings/pending?device_id={$device->device_id}")
        ->assertStatus(204);
});

test('pending seed requires device id parameter', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/pipe/pairings/pending')->assertStatus(422);
});

test('receiver can accept pairing', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $receiverDevice = PipeDevice::factory()->create(['user_id' => $user->id]);
    $senderDevice = PipeDevice::factory()->create();

    $pairing = PipePairing::factory()->create([
        'sender_device_id' => $senderDevice->id,
        'receiver_device_id' => $receiverDevice->id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson("/api/v1/pipe/pairings/{$pairing->id}/accept");

    $response->assertStatus(200)->assertJsonPath('accepted', true);
    $this->assertDatabaseHas('pipe_pairings', ['id' => $pairing->id, 'is_accepted' => true]);
});

test('sender cannot accept pairing on behalf of receiver', function () {
    $senderUser = User::factory()->create();
    $receiverUser = User::factory()->create();
    Sanctum::actingAs($senderUser);

    $senderDevice = PipeDevice::factory()->create(['user_id' => $senderUser->id]);
    $receiverDevice = PipeDevice::factory()->create(['user_id' => $receiverUser->id]);

    $pairing = PipePairing::factory()->create([
        'sender_device_id' => $senderDevice->id,
        'receiver_device_id' => $receiverDevice->id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson("/api/v1/pipe/pairings/{$pairing->id}/accept")->assertStatus(404);
});

test('sender can poll pairing status', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $senderDevice = PipeDevice::factory()->create(['user_id' => $user->id]);
    $receiverDevice = PipeDevice::factory()->create();

    $pairing = PipePairing::factory()->create([
        'sender_device_id' => $senderDevice->id,
        'receiver_device_id' => $receiverDevice->id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->getJson("/api/v1/pipe/pairings/{$pairing->id}");

    $response->assertStatus(200)
        ->assertJsonPath('pairing_id', $pairing->id)
        ->assertJsonPath('is_accepted', false);
});

test('third party cannot view pairing status', function () {
    $thirdParty = User::factory()->create();
    Sanctum::actingAs($thirdParty);

    $pairing = PipePairing::factory()->create([
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson("/api/v1/pipe/pairings/{$pairing->id}")->assertStatus(404);
});

test('can list own active devices', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);
    PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);
    PipeDevice::factory()->create(['user_id' => $otherUser->id, 'expires_at' => now()->addYear()]);
    PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subMinute()]);

    $response = $this->getJson('/api/v1/pipe/devices');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'devices');
});

test('unauthenticated user cannot list devices', function () {
    $this->getJson('/api/v1/pipe/devices')->assertStatus(401);
});

test('can show own device', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $device = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addYear()]);

    $response = $this->getJson("/api/v1/pipe/devices/{$device->device_id}");

    $response->assertStatus(200)
        ->assertJsonPath('device_id', $device->device_id)
        ->assertJsonStructure(['device_id', 'public_key', 'expires_at']);
});

test('cannot show other users device', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    $device = PipeDevice::factory()->create(['user_id' => $otherUser->id, 'expires_at' => now()->addYear()]);

    $this->getJson("/api/v1/pipe/devices/{$device->device_id}")->assertStatus(404);
});

test('show expired device returns 404', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $device = PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subMinute()]);

    $this->getJson("/api/v1/pipe/devices/{$device->device_id}")->assertStatus(404);
});

test('destroy device returns 204 even if not found', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/pipe/devices/DEVXXXX')->assertStatus(204);
});
