<?php

namespace Tests\Feature;

use App\Models\PipeDevice;
use App\Models\PipePairing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PipePairingTest extends TestCase
{
    use RefreshDatabase;

    // ─── Register device ──────────────────────────────────────────────────────

    public function test_authenticated_user_can_register_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/pipe/devices', [
            'public_key' => base64_encode(json_encode(['kty' => 'EC'])),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['device_id', 'expires_at']);

        $this->assertDatabaseCount('pipe_devices', 1);
    }

    public function test_unauthenticated_user_cannot_register_device(): void
    {
        $this->postJson('/api/v1/pipe/devices', [
            'public_key' => base64_encode(json_encode(['kty' => 'EC'])),
        ])->assertStatus(401);
    }

    public function test_register_device_validates_public_key_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/pipe/devices', [])->assertStatus(422);
    }

    // ─── List waiting devices ─────────────────────────────────────────────────

    public function test_can_list_waiting_devices_for_own_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addMinutes(10)]);
        PipeDevice::factory()->create(['user_id' => $otherUser->id, 'expires_at' => now()->addMinutes(10)]);

        $response = $this->getJson('/api/v1/pipe/devices/waiting');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'devices');
    }

    public function test_expired_devices_not_listed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        PipeDevice::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subMinute()]);

        $response = $this->getJson('/api/v1/pipe/devices/waiting');
        $response->assertStatus(200)->assertJsonCount(0, 'devices');
    }

    // ─── Destroy device ───────────────────────────────────────────────────────

    public function test_can_destroy_own_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = PipeDevice::factory()->create(['user_id' => $user->id]);

        $this->deleteJson("/api/v1/pipe/devices/{$device->device_id}")->assertStatus(204);

        $this->assertDatabaseMissing('pipe_devices', ['id' => $device->id]);
    }

    public function test_cannot_destroy_other_users_device(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $device = PipeDevice::factory()->create(['user_id' => $otherUser->id]);

        $this->deleteJson("/api/v1/pipe/devices/{$device->device_id}")->assertStatus(404);
    }

    // ─── Send seed ────────────────────────────────────────────────────────────

    public function test_can_send_seed_to_own_device(): void
    {
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
    }

    public function test_cannot_send_seed_to_another_users_device(): void
    {
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
    }

    // ─── Pending seed ─────────────────────────────────────────────────────────

    public function test_can_poll_pending_seed_for_own_device(): void
    {
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
    }

    public function test_pending_seed_returns_204_when_no_pairing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $device = PipeDevice::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->getJson("/api/v1/pipe/pairings/pending?device_id={$device->device_id}")
            ->assertStatus(204);
    }

    public function test_pending_seed_requires_device_id_parameter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/pipe/pairings/pending')->assertStatus(422);
    }

    // ─── Accept pairing ───────────────────────────────────────────────────────

    public function test_receiver_can_accept_pairing(): void
    {
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
    }

    public function test_sender_cannot_accept_pairing_on_behalf_of_receiver(): void
    {
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
    }

    // ─── Pairing status ───────────────────────────────────────────────────────

    public function test_sender_can_poll_pairing_status(): void
    {
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
    }

    public function test_third_party_cannot_view_pairing_status(): void
    {
        $thirdParty = User::factory()->create();
        Sanctum::actingAs($thirdParty);

        $pairing = PipePairing::factory()->create([
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->getJson("/api/v1/pipe/pairings/{$pairing->id}")->assertStatus(404);
    }
}
