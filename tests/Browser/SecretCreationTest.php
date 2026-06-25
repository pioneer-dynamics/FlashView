<?php

test('guest creates a text secret and receives a share link', function () {
    $page = visit('/')
        ->fill('#message', 'My secret message')
        ->click('Generate link');

    $page->assertSee('Please share the link and password separately');
    $page->assertPresent('[data-testid="share-url"]');
    $page->assertPresent('[data-testid="passphrase"]');
});

test('guest creates a secret with a custom password', function () {
    $page = visit('/')
        ->fill('#message', 'Secret with custom password')
        ->fill('#password', 'my-custom-password')
        ->click('Generate link');

    $page->assertSee('Please share the link and password separately');
    $page->assertPresent('[data-testid="share-url"]');
});

test('authenticated user creates a text secret', function () {
    $user = createTestUser();

    $page = browserLogin($user)
        ->navigate('/')
        ->fill('#message', 'Authenticated user secret')
        ->click('Generate link');

    $page->assertSee('Please share the link and password separately');
    $page->assertPresent('[data-testid="share-url"]');
    $page->assertPresent('[data-testid="passphrase"]');
});
