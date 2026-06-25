<?php

use App\Models\CallParticipant;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('participant belongs to call session', function () {
    $session = CallSession::factory()->create();
    $participant = CallParticipant::factory()->create(['call_session_id' => $session->id]);

    expect($participant->session->is($session))->toBeTrue();
});

test('call session has many participants', function () {
    $session = CallSession::factory()->create();
    CallParticipant::factory()->count(3)->create(['call_session_id' => $session->id]);

    expect($session->participants)->toHaveCount(3);
});

test('ip address is encrypted at rest', function () {
    $session = CallSession::factory()->create();
    $participant = CallParticipant::factory()->create([
        'call_session_id' => $session->id,
        'ip_address' => '192.168.1.100',
    ]);

    // The raw database value should not equal the plaintext IP
    $raw = DB::table('call_participants')
        ->where('id', $participant->id)
        ->value('ip_address');

    $this->assertNotEquals('192.168.1.100', $raw);

    // But the model attribute should transparently decrypt it
    expect($participant->fresh()->ip_address)->toEqual('192.168.1.100');
});
