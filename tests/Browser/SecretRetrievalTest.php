<?php

/**
 * Creates a secret via the UI and returns the share URL, passphrase, and page object.
 *
 * @return array{shareUrl: string, passphrase: string, page: mixed}
 */
function createSecretViaUI(string $message): array
{
    $page = visit('/')
        ->fill('#message', $message)
        ->click('Generate link')
        ->assertSee('Please share the link and password separately');

    return [
        'shareUrl' => trim($page->text('[data-testid="share-url"] code')),
        'passphrase' => trim($page->text('[data-testid="passphrase"] code')),
        'page' => $page,
    ];
}

test('recipient visits share link, enters passphrase, and decrypts message', function () {
    $message = 'Hello from E2E test';
    $result = createSecretViaUI($message);

    $result['page']
        ->navigate($result['shareUrl'])
        ->fill('#password', $result['passphrase'])
        ->click('Retrieve');

    $result['page']->assertSee($message);
});

test('secret is inaccessible after first retrieval (burn-after-reading)', function () {
    $message = 'One-time message';
    $result = createSecretViaUI($message);

    // First retrieval
    $result['page']
        ->navigate($result['shareUrl'])
        ->fill('#password', $result['passphrase'])
        ->click('Retrieve');

    $result['page']->assertSee($message);

    // Second visit — message is null in DB; clicking Retrieve returns empty flash → destroyed state
    $result['page']
        ->navigate($result['shareUrl'])
        ->fill('#password', $result['passphrase'])
        ->click('Retrieve');

    $result['page']->assertPresent('[data-testid="destroyed-state"]');
});

test('expired secret shows appropriate error state', function () {
    $result = createSecretViaUI('Expiring secret');

    // Expire all secrets — replicates what ClearExpiredSecrets job does
    expireAllSecrets();

    // The share URL still renders the view — destroyed state only appears after clicking Retrieve
    $result['page']
        ->navigate($result['shareUrl'])
        ->fill('#password', $result['passphrase'])
        ->click('Retrieve');

    $result['page']->assertPresent('[data-testid="destroyed-state"]');
});
