<?php

test('navigating to /calls renders the Secure Line index page with a bridge number input', function () {
    $page = visit('/calls');

    $page->assertSee('Encrypted, Ephemeral Audio Calls');
    $page->assertVisible('[data-testid="bridge-number-input"]');
    $page->assertVisible('[data-testid="join-line-button"]');
});

test('entering a bridge number and clicking Join Line navigates to the join page', function () {
    $session = createActiveCallSession();

    $page = visit('/calls');
    $page->fill('[data-testid="bridge-number-input"]', $session->hash_id);
    $page->click('[data-testid="join-line-button"]');

    $page->assertPathIs('/calls/'.$session->hash_id);
});

test('pressing Enter in the bridge number input navigates to the join page', function () {
    $session = createActiveCallSession();

    $page = visit('/calls');
    $page->fill('[data-testid="bridge-number-input"]', $session->hash_id);
    $page->press('Enter');

    $page->assertPathIs('/calls/'.$session->hash_id);
});

test('submitting an empty bridge number does not navigate', function () {
    $page = visit('/calls');

    $page->click('[data-testid="join-line-button"]');

    $page->assertPathIs('/calls');
});

test('the Join Line button is disabled when the bridge number input is empty', function () {
    $page = visit('/calls');

    $page->assertDisabled('[data-testid="join-line-button"]');
});

test('the Buy a Line card is visible with a link to plans', function () {
    $page = visit('/calls');

    $page->assertSee('Buy a Line');
    $page->assertSee('Buy a Line →');
});
