<?php

// ─── Inline helpers ────────────────────────────────────────────────────────────

/**
 * Creates a passphrase-only locker via the UI and returns ['accountId', 'passphrase'].
 *
 * @return array{accountId: string, passphrase: string}
 */
function createLockerViaUIShow(string $accountId, string $passphrase, string $content): array
{
    $credit = createLockerCredit();
    visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', $accountId)
        ->fill('input[placeholder="Enter or generate a passphrase"]', $passphrase)
        ->fill('textarea[placeholder="Enter the content to store…"]', $content)
        ->click('Encrypt & Create')
        ->assertSee('Locker created!');

    return ['accountId' => $accountId, 'passphrase' => $passphrase];
}

/**
 * Navigates to /lockers/open, enters the account ID, and proceeds to the unlock form.
 * Returns $page positioned at the unlock form.
 */
function navigateToLockerShow(string $accountId): mixed
{
    return visit('/lockers/open')
        ->fill('[data-testid="account-id-input"]', $accountId)
        ->click('[data-testid="open-button"]')
        ->assertVisible('[data-testid="unlock-button"]');
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('visiting old locker URL redirects to open page', function () {
    $page = visit('/lockers/9999999999');

    $page->assertPathIs('/lockers/open');
    $page->assertVisible('[data-testid="account-id-input"]');
});

test('navigating to /lockers/open shows account entry form', function () {
    $page = visit('/lockers/open');

    $page->assertVisible('[data-testid="account-id-input"]');
    $page->assertVisible('[data-testid="open-button"]');
});

test('entering 10-digit account number transitions to unlock form; URL stays at /lockers/open', function () {
    createLockerViaUIShow('1111111111', 'entry-phase-passphrase', 'Entry phase test');

    $page = visit('/lockers/open');

    $page->assertPathIs('/lockers/open');
    $page->fill('[data-testid="account-id-input"]', '1111111111');
    $page->click('[data-testid="open-button"]');

    $page->assertVisible('[data-testid="unlock-button"]');
    $page->assertPathIs('/lockers/open');
    $page->assertVisible('[data-testid="passphrase-input"]');
});

test('renewed=1 query parameter shows renewal banner with account entry instruction', function () {
    $page = visit('/lockers/open?renewed=1');

    $page->assertSee('Your renewal was successful');
    $page->assertSee('Enter your account number below');
    $page->assertVisible('[data-testid="account-id-input"]');
});

test('lock icon is visible before unlock is attempted', function () {
    createLockerViaUIShow('2222222222', 'my-show-passphrase-long', 'Test content');

    $page = navigateToLockerShow('2222222222');

    $page->assertVisible('[data-testid="lock-icon"]');
    $page->assertVisible('[data-testid="passphrase-input"]');
    $page->assertVisible('[data-testid="unlock-button"]');
});

test('correct passphrase decrypts and displays content', function () {
    $result = createLockerViaUIShow('3333333333', 'my-correct-passphrase-long', 'My secret locker text');

    $page = navigateToLockerShow('3333333333');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');

    $page->assertVisible('[data-testid="decrypted-content"]');
    $page->assertSee('My secret locker text');
});

test('URL stays at /lockers/open throughout the unlock flow', function () {
    $result = createLockerViaUIShow('3344556677', 'url-check-passphrase-long', 'URL check content');

    $page = navigateToLockerShow('3344556677');
    $page->assertPathIs('/lockers/open');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->assertPathIs('/lockers/open');
});

test('after submitting correct passphrase, lock animation plays and content is revealed', function () {
    $result = createLockerViaUIShow('4444444444', 'my-anim-passphrase-long', 'Animation test content');

    $page = navigateToLockerShow('4444444444');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');

    // Button should show "Unlocking…" during animation
    $page->assertSee('Unlocking');

    // Content should appear after animation completes
    $page->assertVisible('[data-testid="decrypted-content"]');
});

test('wrong passphrase shows error and lock shake animation', function () {
    createLockerViaUIShow('5555555555', 'my-real-passphrase-long', 'Content for wrong pass test');

    $page = navigateToLockerShow('5555555555');

    $page->fill('[data-testid="passphrase-input"]', 'wrong-passphrase-here-!');
    $page->click('[data-testid="unlock-button"]');

    $page->assertVisible('[data-testid="decrypt-error"]');
});

test('after submitting wrong passphrase, error message appears and lock icon stays visible', function () {
    createLockerViaUIShow('6666666666', 'my-real-passphrase-long', 'Content for shake test');

    $page = navigateToLockerShow('6666666666');

    $page->fill('[data-testid="passphrase-input"]', 'wrong!');
    $page->click('[data-testid="unlock-button"]');

    // Error message should appear
    $page->assertVisible('[data-testid="decrypt-error"]');

    // Lock icon should still be visible (not replaced by content)
    $page->assertVisible('[data-testid="lock-icon"]');
});

test('repeated wrong passphrase shows permanent loss warning', function () {
    createLockerViaUIShow('7777777777', 'my-real-passphrase-long', 'Content for repeated fail test');

    $page = navigateToLockerShow('7777777777');

    // First wrong attempt
    $page->fill('[data-testid="passphrase-input"]', 'wrong-pass-attempt');
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypt-error"]');

    // Second wrong attempt
    $page->fill('[data-testid="passphrase-input"]', 'wrong-pass-attempt');
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypt-error"]');

    $page->assertSee('credentials are lost');
});

test('update hint visible in locked state; update panel visible after unlock', function () {
    $result = createLockerViaUIShow('8888888888', 'my-real-passphrase-long', 'Content for update panel test');

    $page = navigateToLockerShow('8888888888');

    // Locked state: shows hint, not the update panel
    $page->assertSee('Unlock your locker to update or delete it.');

    // Unlock to see the update panel
    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->assertSee('Update Content');
    $page->assertVisible('[data-testid="update-button"]');
});

test('expiry badge is visible after unlock', function () {
    $result = createLockerViaUIShow('9999111111', 'my-real-passphrase-long', 'Content for expiry badge test');

    $page = navigateToLockerShow('9999111111');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->assertSee('Renew');
});

// ─── DEK-based file locker flows (PIO-102) ───────────────────────────────────

test('file locker shows file metadata after unlock', function () {
    todo('Requires page.route() network interception for S3 mock download URL — not available in pest-plugin-browser v4');
});

test('passphrase change on DEK-based file locker does not re-download the file', function () {
    todo('Requires page.route() network interception and page.on(request) monitoring — not available in pest-plugin-browser v4');
});

// ─── ECDSA flows (PIO-103) ────────────────────────────────────────────────────

test('ECDSA locker unlock decrypts and displays content', function () {
    $result = createLockerViaUIShow('5100000001', 'ecdsa-unlock-passphrase', 'ECDSA secret text');

    $page = navigateToLockerShow('5100000001');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');

    $page->assertVisible('[data-testid="decrypted-content"]');
    $page->assertSee('ECDSA secret text');
});

test('ECDSA locker update content shows success message', function () {
    $result = createLockerViaUIShow('5100000002', 'ecdsa-update-passphrase', 'Original content');

    $page = navigateToLockerShow('5100000002');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->fill('textarea[placeholder="New content…"]', 'Updated secret content');
    $page->click('[data-testid="update-button"]');

    $page->assertSee('Content updated.');
    $page->assertSee('Updated secret content');
});

test('ECDSA locker delete redirects to home', function () {
    $result = createLockerViaUIShow('5100000003', 'ecdsa-delete-passphrase', 'Content to delete');

    $page = navigateToLockerShow('5100000003');

    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->click('Delete this locker permanently');
    $page->click('[data-testid="confirm-delete-button"]');

    $page->assertPathIs('/');
});

test('ECDSA locker passphrase change — new passphrase unlocks, old one fails', function () {
    $oldPassphrase = 'ecdsa-old-passphrase-secure';
    $newPassphrase = 'ecdsa-new-passphrase-secure';
    $result = createLockerViaUIShow('5100000004', $oldPassphrase, 'Passphrase change content');
    $accountId = $result['accountId'];

    // Unlock with old passphrase and change it
    $page = navigateToLockerShow($accountId);

    $page->fill('[data-testid="passphrase-input"]', $oldPassphrase);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    // ECDSA lockers use the credential rotation panel
    $page->fill('[data-testid="new-passphrase-rot-input"]', $newPassphrase);
    $page->click('[data-testid="rotate-credentials-button"]');
    $page->assertSee('Credentials changed');

    // Lock — returns to account entry phase
    $page->click('[data-testid="lock-button"]');

    // Re-enter account number
    $page->fill('[data-testid="account-id-input"]', $accountId);
    $page->click('[data-testid="open-button"]');
    $page->assertVisible('[data-testid="unlock-button"]');

    // Try old passphrase — should fail
    $page->fill('[data-testid="passphrase-input"]', $oldPassphrase);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypt-error"]');

    // Try new passphrase — should succeed
    $page->fill('[data-testid="passphrase-input"]', $newPassphrase);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');
});

test('upgrade banner appears after legacy unlock and upgrade migrates to ECDSA', function () {
    todo('Requires page.route() and page.context().route() network interception to mock legacy challenge/unlock/upgrade-auth endpoints — not available in pest-plugin-browser v4');
});

test('upgrade banner can be dismissed without upgrading', function () {
    todo('Requires page.context().route() network interception to mock legacy challenge/unlock endpoints — not available in pest-plugin-browser v4');
});
