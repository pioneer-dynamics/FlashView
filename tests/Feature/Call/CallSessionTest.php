<?php

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('call session can be created with factory', function () {
    $session = CallSession::factory()->create();

    $this->assertDatabaseHas('call_sessions', ['id' => $session->id]);
    expect($session->public_key)->not->toBeEmpty();
    expect($session->key_salt)->not->toBeEmpty();
});

test('hash id is computed from integer pk', function () {
    $session = CallSession::factory()->create();

    expect($session->hash_id)->not->toBeEmpty();
    expect($session->hash_id)->toBeString();
    expect(strlen($session->hash_id))->toEqual(10);
});

test('active scope returns sessions within time window', function () {
    $active = CallSession::factory()->create([
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
    ]);

    $this->assertDatabaseHas('call_sessions', ['id' => $active->id]);
    expect(CallSession::active()->get())->toHaveCount(1);
});

test('active scope excludes sessions outside time window', function () {
    CallSession::factory()->inactive()->create();
    CallSession::factory()->notYetStarted()->create();

    expect(CallSession::active()->get())->toHaveCount(0);
});

test('joinable scope delegates to active', function () {
    $active = CallSession::factory()->create();
    CallSession::factory()->inactive()->create();

    $joinable = CallSession::joinable()->get();

    expect($joinable)->toHaveCount(1);
    expect($joinable->first()->is($active))->toBeTrue();
});

test('is full returns true when participants at max', function () {
    $session = CallSession::factory()->create(['max_participants' => 2]);
    CallParticipant::factory()->count(2)->create(['call_session_id' => $session->id]);

    expect($session->isFull())->toBeTrue();
});

test('is full returns false when below max', function () {
    $session = CallSession::factory()->create(['max_participants' => 2]);
    CallParticipant::factory()->create(['call_session_id' => $session->id]);

    expect($session->isFull())->toBeFalse();
});

test('is active returns true for active session', function () {
    $session = CallSession::factory()->create();

    expect($session->isActive())->toBeTrue();
});

test('is active returns false for inactive session', function () {
    $session = CallSession::factory()->inactive()->create();

    expect($session->isActive())->toBeFalse();
});
