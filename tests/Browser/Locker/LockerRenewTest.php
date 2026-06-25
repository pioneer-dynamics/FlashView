<?php

// ─── Inline helpers ────────────────────────────────────────────────────────────

/**
 * Creates a passphrase-only locker via the UI and returns ['accountId', 'passphrase'].
 *
 * @return array{accountId: string, passphrase: string}
 */
function createLockerViaUIRenew(string $accountId, string $passphrase, string $content): array
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
 * Creates a key-file locker via the UI and returns ['accountId'].
 *
 * @param  string[]  $keyFilePaths
 * @return array{accountId: string}
 */
function createKeyFileLockerViaUIRenew(string $accountId, string $content, array $keyFilePaths): array
{
    $credit = createLockerCredit();
    $page = visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', $accountId)
        ->click('Key File(s)');

    foreach ($keyFilePaths as $path) {
        $page->attach('[data-testid="key-file-input"]', $path);
    }

    $page->check('[data-testid="key-file-risk-checkbox"]')
        ->fill('textarea[placeholder="Enter the content to store…"]', $content)
        ->click('[data-testid="create-submit-button"]')
        ->assertSee('Locker created!');

    return ['accountId' => $accountId];
}

/**
 * Creates a combined (passphrase + key file) locker via the UI and returns ['accountId', 'passphrase'].
 *
 * @param  string[]  $keyFilePaths
 * @return array{accountId: string, passphrase: string}
 */
function createCombinedLockerViaUIRenew(string $accountId, string $passphrase, string $content, array $keyFilePaths): array
{
    $credit = createLockerCredit();
    $page = visit('/lockers/create?token='.$credit->token)
        ->fill('input[placeholder="Choose a 10-digit number"]', $accountId)
        ->click('Both')
        ->fill('input[placeholder="Enter or generate a passphrase"]', $passphrase);

    foreach ($keyFilePaths as $path) {
        $page->attach('[data-testid="key-file-input"]', $path);
    }

    $page->check('[data-testid="key-file-risk-checkbox"]')
        ->fill('textarea[placeholder="Enter the content to store…"]', $content)
        ->click('[data-testid="create-submit-button"]')
        ->assertSee('Locker created!');

    return ['accountId' => $accountId, 'passphrase' => $passphrase];
}

/**
 * Navigates to /lockers/open, enters the account ID, and proceeds to the unlock form.
 * Returns $page positioned at the unlock form.
 */
function navigateToLockerOpenRenew(string $accountId): mixed
{
    return visit('/lockers/open')
        ->fill('[data-testid="account-id-input"]', $accountId)
        ->click('[data-testid="open-button"]')
        ->assertVisible('[data-testid="unlock-button"]');
}

/**
 * Navigates to /lockers/renew, using sessionStorage to pre-fill the account ID.
 * Mirrors the TypeScript navigateToLockerRenew helper.
 */
function navigateToLockerRenew(string $accountId): mixed
{
    $page = visit('/lockers/open');
    $page->script("sessionStorage.setItem('locker_prefill_account_renew', '$accountId')");

    return $page->navigate('/lockers/renew');
}

// ─── Key file temp-file lifecycle ─────────────────────────────────────────────

beforeEach(function () {
    $this->keyFilePaths = [];
});

afterEach(function () {
    foreach ($this->keyFilePaths as $path) {
        @unlink($path);
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

test('renew link on open page leads to renew page without account number in URL', function () {
    $result = createLockerViaUIRenew('1010101010', 'renew-test-passphrase-long', 'Content for renew test');

    $page = navigateToLockerOpenRenew('1010101010');
    $page->fill('[data-testid="passphrase-input"]', $result['passphrase']);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->click('[data-testid="renew-button"]');

    $page->assertPathEndsWith('/renew');
    $page->assertSee('Renew eLocker');
    $page->assertVisible('[data-testid="passphrase-input"]');
});

test('renew page displays current tier', function () {
    createLockerViaUIRenew('2020202020', 'renew-test-passphrase-long', 'Content for tier display test');

    $page = navigateToLockerRenew('2020202020');

    $page->assertSee('Text Locker');
});

test('renew page shows duration selection buttons', function () {
    createLockerViaUIRenew('3030303030', 'renew-test-passphrase-long', 'Content for duration test');

    $page = navigateToLockerRenew('3030303030');

    $page->assertSee('1yr');
    $page->assertSee('3yr');
    $page->assertSee('5yr');
});

test('wrong passphrase on renew shows error', function () {
    createLockerViaUIRenew('4040404040', 'renew-test-passphrase-long', 'Content for wrong pass test');

    $page = navigateToLockerRenew('4040404040');

    $page->fill('[data-testid="passphrase-input"]', 'wrong-passphrase-!');
    $page->click('[data-testid="renew-submit-button"]');

    $page->assertSee('Invalid passphrase');
});

// ─── Key-file renewal ─────────────────────────────────────────────────────────

test('key-file locker renew page shows key file inputs (not passphrase)', function () {
    $alpha = keyFileAlpha();
    $this->keyFilePaths[] = $alpha;

    createKeyFileLockerViaUIRenew('5050505050', 'Key-file renew content', [$alpha]);

    $page = navigateToLockerRenew('5050505050');

    $page->assertVisible('[data-testid="key-file-input-label"]');
    $page->assertMissing('[data-testid="passphrase-input"]');
});

test('combined locker renew page shows both passphrase and key file inputs', function () {
    $alpha = keyFileAlpha();
    $this->keyFilePaths[] = $alpha;

    createCombinedLockerViaUIRenew('6060606060', 'combined-renew-pass', 'Combined renew content', [$alpha]);

    $page = navigateToLockerRenew('6060606060');

    $page->assertVisible('[data-testid="passphrase-input"]');
    $page->assertVisible('[data-testid="key-file-input-label"]');
});

// ─── ECDSA passphrase renewal (PIO-103) ───────────────────────────────────────

test('ECDSA locker renewal with correct passphrase redirects to Stripe checkout', function () {
    todo('Requires page.route() to mock Stripe checkout redirect — not available in pest-plugin-browser v4');
});

test('ECDSA locker renewal with wrong passphrase shows error', function () {
    createLockerViaUIRenew('8080808080', 'ecdsa-renew-correct-pass', 'Renewal wrong pass test');

    $page = navigateToLockerRenew('8080808080');

    $page->fill('[data-testid="passphrase-input"]', 'totally-wrong-passphrase!');
    $page->click('[data-testid="renew-submit-button"]');

    $page->assertSee('Invalid passphrase');
});

// ─── Direct navigation (no sessionStorage) ───────────────────────────────────

test('renew page without sessionStorage redirects to locker index', function () {
    $page = visit('/lockers/renew');

    $page->assertPathIs('/lockers');
});
