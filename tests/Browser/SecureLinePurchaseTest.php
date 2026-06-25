<?php

// ── Call/Index buy button ────────────────────────────────────────────────────

test('Buy a Line card links to /calls/buy', function () {
    $page = visit('/calls');

    $page->assertSee('Buy a Line →');
    $page->click('Buy a Line →');

    $page->assertPathIs('/calls/buy');
});

// ── Buy page ─────────────────────────────────────────────────────────────────

test('buy page shows "How it works" block and active products', function () {
    createSecureLineProduct(['name' => 'Quick Call', 'amount_cents' => 2000, 'stripe_price_id' => 'price_test_e2e']);

    $page = visit('/calls/buy');

    $page->assertSee('How it works');
    $page->assertSee('Pay once — no account, no subscription.');
    $page->assertSee('Receive a bridge number and a call password.');
    $page->assertSee('Your call window starts the moment');
    $page->assertSee('Quick Call');
    $page->assertSeeIn('[data-testid="product-price"]', '$20');
    $page->assertVisible('[data-testid="purchase-button"]');
});

test('buy page shows empty state when no products', function () {
    $page = visit('/calls/buy');

    $page->assertSee('No Secure Line products are currently available.');
});

test('buy page excludes inactive products', function () {
    createSecureLineProduct(['name' => 'Hidden Product', 'stripe_price_id' => 'price_test_e2e', 'is_active' => false]);

    $page = visit('/calls/buy');

    $page->assertDontSee('Hidden Product');
});

// ── Pending token recovery banner ────────────────────────────────────────────

test('buy page shows recovery banner when pending token in localStorage', function () {
    $page = visit('/calls/buy');
    $page->script("localStorage.setItem('secure_line_pending_token', 'recovery-test-token')");

    // Reload to trigger the onMounted check
    $page->reload();

    $page->assertSee('Unused Secure Line Credit');
    $page->assertSee('Continue →');
    $page->assertSee('Dismiss');
});

test('dismissing the recovery banner removes it', function () {
    $page = visit('/calls/buy');
    $page->script("localStorage.setItem('secure_line_pending_token', 'dismiss-test-token')");

    $page->reload();

    $page->click('Dismiss');

    $page->assertDontSee('Unused Secure Line Credit');
});

// ── AwaitCredit page ─────────────────────────────────────────────────────────

test('await-credit page shows shimmer and payment reference initially', function () {
    $page = visit('/calls/await-credit?session=cs_test_e2e_fake');

    $page->assertSee('Confirming your payment…');
    $page->assertSee('cs_test_e2e_fake');
    $page->assertSee('Waiting for Stripe confirmation…');
});

test('await-credit shows retry button after timeout', function () {
    todo('Requires page.route() and page.clock — both unavailable in pest-plugin-browser v4');
});

// ── Create page ───────────────────────────────────────────────────────────────

test('create page shows 404 for invalid token', function () {
    $this->get('/calls/create?token=notexists')->assertStatus(404);
});

test('create page shows 404 for used credit token', function () {
    $credit = createSecureLineCredit(used: true);

    $this->get('/calls/create?token='.$credit->token)->assertStatus(404);
});

test('full happy path: create page generates credentials and shows them', function () {
    $credit = createSecureLineCredit();

    $page = visit('/calls/create?token='.$credit->token);

    // Wait for credentials panel to appear (crypto + API call)
    $page->assertVisible('[data-testid="bridge-number"]');

    // Bridge number and password are shown
    $page->assertPresent('[data-testid="bridge-number"]');
    $page->assertPresent('[data-testid="call-password"]');

    // Session expiry is shown
    $page->assertVisible('[data-testid="session-expiry"]');

    // Copy buttons present
    $page->assertVisible('[data-testid="copy-bridge-number"]');
    $page->assertVisible('[data-testid="copy-call-password"]');

    // Download button present
    $page->assertVisible('[data-testid="download-credentials"]');

    // Done button is disabled until checkbox is checked
    $page->assertDisabled('[data-testid="done-button"]');

    // Check the "I have saved" checkbox
    $page->check('[data-testid="saved-confirmed-checkbox"]');

    // Done button is now enabled
    $page->assertEnabled('[data-testid="done-button"]');
});

test('done button navigates to /calls after confirming credentials saved', function () {
    $credit = createSecureLineCredit();

    $page = visit('/calls/create?token='.$credit->token);

    $page->assertVisible('[data-testid="bridge-number"]');

    $page->check('[data-testid="saved-confirmed-checkbox"]');
    $page->click('[data-testid="done-button"]');

    $page->assertPathIs('/calls');
});

test('create page removes pending token from localStorage on success', function () {
    $credit = createSecureLineCredit();

    // Pre-set the pending token
    $page = visit('/calls');
    $page->script("localStorage.setItem('secure_line_pending_token', '".$credit->token."')");

    $page->navigate('/calls/create?token='.$credit->token);

    $page->assertVisible('[data-testid="bridge-number"]');

    $pendingToken = $page->script("return localStorage.getItem('secure_line_pending_token')");
    expect($pendingToken)->toBeNull();
});

test('participant instructions are shown on credentials page', function () {
    $credit = createSecureLineCredit();

    $page = visit('/calls/create?token='.$credit->token);

    $page->assertVisible('[data-testid="bridge-number"]');
    $page->assertSee('Join a Line');
});
