<?php

use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('call index page renders', function () {
    $response = $this->get('/calls');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('Call/Index'));
});

test('call join page renders for active session', function () {
    $session = CallSession::factory()->create();

    $response = $this->get("/calls/{$session->hash_id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Call/Join')
            ->where('session.bridge_number', $session->hash_id)
            ->where('session.is_active', true)
        );
});

test('call join page shows future session as not active', function () {
    $session = CallSession::factory()->notYetStarted()->create();

    $response = $this->get("/calls/{$session->hash_id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Call/Join')
            ->where('session.is_active', false)
        );
});

test('call join page returns 404 for invalid hash', function () {
    $response = $this->get('/calls/invalidhash');

    $response->assertNotFound();
});

test('call room page renders', function () {
    $session = CallSession::factory()->create();

    $response = $this->get("/calls/{$session->hash_id}/room");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Call/Room')
            ->where('session.bridge_number', $session->hash_id)
        );
});

test('call room page returns 404 for invalid hash', function () {
    $response = $this->get('/calls/invalidhash/room');

    $response->assertNotFound();
});
