<?php

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command outputs session and participants', function () {
    $session = CallSession::factory()->create();
    CallParticipant::factory()->create(['call_session_id' => $session->id]);

    $this->artisan('legal:call-metadata', ['hash_id' => $session->hash_id])
        ->assertSuccessful()
        ->expectsOutputToContain($session->hash_id)
        ->expectsOutputToContain('Session')
        ->expectsOutputToContain('Participants');
});

test('command decrypts ip address in output', function () {
    $session = CallSession::factory()->create();
    CallParticipant::factory()->create([
        'call_session_id' => $session->id,
        'ip_address' => '10.0.0.1',
    ]);

    $this->artisan('legal:call-metadata', ['hash_id' => $session->hash_id])
        ->assertSuccessful()
        ->expectsOutputToContain('10.0.0.1');
});

test('command fails for unknown bridge number', function () {
    $this->artisan('legal:call-metadata', ['hash_id' => 'unknownhash'])
        ->assertFailed()
        ->expectsOutputToContain('not found');
});

test('command outputs empty participants table when already purged', function () {
    $session = CallSession::factory()->create();

    $this->artisan('legal:call-metadata', ['hash_id' => $session->hash_id])
        ->assertSuccessful()
        ->expectsOutputToContain('Participants');
});
