<?php

test('navigating to an active session renders the Join page with the bridge number', function () {
    $session = createActiveCallSession();

    $page = visit('/calls/'.$session->hash_id);

    $page->assertSee($session->hash_id);
    $page->assertVisible('[data-testid="call-password-input"]');
    $page->assertVisible('[data-testid="join-call-button"]');
});

test('navigating to a future session shows a "not yet started" message and a disabled button', function () {
    $session = createFutureCallSession();

    $page = visit('/calls/'.$session->hash_id);

    $page->assertSee('Not Yet Started');
    $page->assertSee('This call starts at');
    $page->assertDisabled('button:has-text("Join Call")');
});

test('navigating to an invalid bridge number returns a 404 page', function () {
    $this->get('/calls/invalidhash000')->assertStatus(404);
});

test('the Join Call button is disabled when password input is empty', function () {
    $session = createActiveCallSession();

    $page = visit('/calls/'.$session->hash_id);

    $page->assertDisabled('[data-testid="join-call-button"]');
});

test('typing in the password field enables the Join Call button', function () {
    $session = createActiveCallSession();

    $page = visit('/calls/'.$session->hash_id);
    $page->fill('[data-testid="call-password-input"]', 'any-password');

    $page->assertEnabled('[data-testid="join-call-button"]');
});
