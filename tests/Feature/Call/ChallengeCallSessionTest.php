<?php

use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('challenge returns challenge and salt for valid hash id', function () {
    $session = CallSession::factory()->create();

    $response = $this->getJson("/call-sessions/{$session->hash_id}/challenge");

    $response->assertOk()
        ->assertJsonStructure(['challenge', 'salt'])
        ->assertJsonPath('salt', $session->key_salt);

    expect(strlen($response->json('challenge')))->toEqual(64);
});

test('challenge returns 404 for unknown hash id', function () {
    $response = $this->getJson('/call-sessions/unknownhash/challenge');

    $response->assertNotFound();
});

test('challenge is stored in cache with 60 second ttl', function () {
    $session = CallSession::factory()->create();

    $response = $this->getJson("/call-sessions/{$session->hash_id}/challenge");

    $response->assertOk();

    $cached = Cache::get("call-challenge:{$session->id}");
    expect($cached)->not->toBeNull();
    expect($cached)->toEqual($response->json('challenge'));
});
