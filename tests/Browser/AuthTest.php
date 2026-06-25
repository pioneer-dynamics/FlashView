<?php

test('user can log in with valid credentials', function () {
    $user = createTestUser();

    $page = visit('/login')
        ->fill('#email', $user->email)
        ->fill('#password', 'password')
        ->click('button[type="submit"]');

    $page->assertPathContains('dashboard');
});

test('login fails with invalid password', function () {
    $user = createTestUser();

    $page = visit('/login')
        ->fill('#email', $user->email)
        ->fill('#password', 'wrong-password')
        ->click('button[type="submit"]');

    $page->assertSee('These credentials do not match our records');
});

test('authenticated user can log out', function () {
    $user = createTestUser();

    $page = browserLogin($user)
        ->click('[data-testid="user-menu-trigger"]')
        ->click('Log Out');

    $page->assertPathIs('/');
});
