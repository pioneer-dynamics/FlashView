<?php

use App\Models\PipeSession;
use App\Models\PipeSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sessionId = 'abcdef1234567890abcdef1234567891';
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->session = PipeSession::factory()->create([
        'session_id' => $this->sessionId,
        'user_id' => $this->user->id,
        'expires_at' => now()->addMinutes(10),
    ]);
});

test('can store signal', function () {
    $response = $this->postJson("/api/v1/pipe/{$this->sessionId}/signal", [
        'role' => 'sender',
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0...'],
    ]);

    $response->assertStatus(201)->assertJsonStructure(['signal_id']);
    $this->assertDatabaseCount('pipe_signals', 1);
});

test('invalid role returns 422', function () {
    $this->postJson("/api/v1/pipe/{$this->sessionId}/signal", [
        'role' => 'unknown',
        'type' => 'offer',
        'payload' => [],
    ])->assertStatus(422);
});

test('invalid type returns 422', function () {
    $this->postJson("/api/v1/pipe/{$this->sessionId}/signal", [
        'role' => 'sender',
        'type' => 'invalid-type',
        'payload' => [],
    ])->assertStatus(422);
});

test('can poll signals after id', function () {
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
});

test('polling signals requires valid role', function () {
    $this->getJson("/api/v1/pipe/{$this->sessionId}/signal?role=invalid")
        ->assertStatus(422);
});

test('signal to expired session returns 404', function () {
    $expiredSession = PipeSession::factory()->create([
        'session_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa0',
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/v1/pipe/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa0/signal', [
        'role' => 'sender',
        'type' => 'offer',
        'payload' => ['sdp' => 'v=0'],
    ])->assertStatus(404);
});
