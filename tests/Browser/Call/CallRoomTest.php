<?php

/**
 * Full WebRTC room interaction tests (media tracks, peer connection establishment)
 * require two concurrent browser contexts with media stream mocking. These are
 * deferred to a dedicated follow-up E2E ticket.
 *
 * This file covers only the guards and expiry-adjacent behaviours that can be
 * tested without live media or a second participant.
 */
test('navigating directly to /room without sessionStorage redirects to the join page', function () {
    $session = createActiveCallSession();

    $page = visit('/calls/'.$session->hash_id.'/room');

    // Room.vue reads sessionStorage on mount; if missing it immediately redirects
    $page->assertPathIs('/calls/'.$session->hash_id);
    $page->assertVisible('[data-testid="call-password-input"]');
});

test('navigating to an invalid bridge number on /room returns a 404', function () {
    $this->get('/calls/invalidhash000/room')->assertStatus(404);
});
