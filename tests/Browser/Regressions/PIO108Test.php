<?php

/**
 * Creates a locker via the browser UI and returns accountId/passphrase.
 *
 * @return array{accountId: string, passphrase: string}
 */
function createPIO108LockerViaUI(string $accountId, string $passphrase, string $content): array
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
 * Navigates to a locker by filling the account ID on the open page.
 * Returns the page positioned at the unlock form.
 */
function navigateToPIO108Locker(string $accountId): mixed
{
    return visit('/lockers/open')
        ->fill('[data-testid="account-id-input"]', $accountId)
        ->click('[data-testid="open-button"]')
        ->assertVisible('[data-testid="unlock-button"]');
}

test('renew navigation from index page does not include account number in URL', function () {
    createPIO108LockerViaUI('1081081081', 'pio108-pass-long', 'Regression 108 content');

    $page = visit('/lockers');
    $page->fill('input[placeholder*="10-digit"]', '1081081081');
    $page->click('Renew');
    $page->click('Go to Renew');

    $page->assertPathIs('/lockers/renew');
    expect($page->script('return window.location.href'))->not->toContain('1081081081');
});

test('renew page without sessionStorage redirects to locker index', function () {
    $page = visit('/lockers/renew');

    $page->assertPathContains('/lockers');
});

test('renew link from open page navigates to /lockers/renew without account number in URL', function () {
    ['passphrase' => $passphrase] = createPIO108LockerViaUI('1082082082', 'pio108-open-renew-pass', 'Open renew regression content');

    $page = navigateToPIO108Locker('1082082082');
    $page->fill('[data-testid="passphrase-input"]', $passphrase);
    $page->click('[data-testid="unlock-button"]');
    $page->assertVisible('[data-testid="decrypted-content"]');

    $page->click('[data-testid="renew-button"]');

    $page->assertPathIs('/lockers/renew');
    expect($page->script('return window.location.href'))->not->toContain('1082082082');
});
