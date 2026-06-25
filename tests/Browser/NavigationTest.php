<?php

// ─── Guest Navigation ──────────────────────────────────────────────────────────

test('guest sees correct top-level nav links', function () {
    $page = visit('/');

    $page->assertSee('New Secret');
    $page->assertSee('Pricing');
    $page->assertSee('Secure Line');
    $page->assertSee('Blog');
    $page->assertSee('F.A.Q.');
    $page->assertSee('About Us');
    $page->assertSee('Log in');
    $page->assertSee('Register');
});

test('guest New Secret nav link points to /', function () {
    visit('/plans')
        ->click('nav a[href="/"]')
        ->assertPathIs('/');
});

test('guest Pricing nav link navigates to /plans', function () {
    visit('/')
        ->click('nav a[href="/plans"]')
        ->assertPathIs('/plans');
});

test('guest eLocker dropdown shows Access and Buy links', function () {
    $page = visit('/');

    // Click the eLocker dropdown button (first nav button is the eLocker trigger for guests)
    $page->click('nav button');

    $page->assertSee('Access My Locker');
    $page->assertSee('Buy a Locker');
});

test('guest eLocker Access link navigates to /lockers', function () {
    $page = visit('/');
    $page->click('nav button');
    $page->click('Access My Locker');
    $page->assertPathIs('/lockers');
});

test('guest eLocker Buy link navigates to /lockers/buy', function () {
    $page = visit('/');
    $page->click('nav button');
    $page->click('Buy a Locker');
    $page->assertPathIs('/lockers/buy');
});

test('guest Secure Line nav link navigates to /calls', function () {
    visit('/')
        ->click('nav a[href="/calls"]')
        ->assertPathIs('/calls');
});

test('guest Blog nav link navigates to /blog', function () {
    visit('/')
        ->click('nav a[href="/blog"]')
        ->assertPathIs('/blog');
});

test('guest FAQ nav link navigates to /faq', function () {
    visit('/')
        ->click('nav a[href="/faq"]')
        ->assertPathIs('/faq');
});

test('guest About Us nav link navigates to /about', function () {
    visit('/')
        ->click('nav a[href="/about"]')
        ->assertPathIs('/about');
});

test('guest Log in link navigates to /login', function () {
    visit('/')
        ->click('nav a[href="/login"]')
        ->assertPathIs('/login');
});

test('guest Register link navigates to /register', function () {
    visit('/')
        ->click('nav a[href="/register"]')
        ->assertPathIs('/register');
});

// ─── Active-state highlighting ─────────────────────────────────────────────────

test('Pricing nav link has active state when on /plans', function () {
    $page = visit('/plans');

    $page->assertAttributeContains('nav a[href="/plans"]', 'class', 'border-gamboge-700');
});

test('Secure Line nav link has active state when on /calls', function () {
    $page = visit('/calls');

    $page->assertAttributeContains('nav a[href="/calls"]', 'class', 'border-gamboge-700');
});

test('Blog nav link has active state when on /blog', function () {
    $page = visit('/blog');

    $page->assertAttributeContains('nav a[href="/blog"]', 'class', 'border-gamboge-700');
});

// ─── Authenticated User Navigation ────────────────────────────────────────────

test('authenticated user sees New Secret and My Secrets nav links', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->assertSee('New Secret');
    $page->assertSee('My Secrets');
});

test('authenticated user does not see Login or Register links', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->assertDontSee('Log in');
    $page->assertDontSee('Register');
});

test('authenticated user New Secret nav link navigates to /dashboard', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->navigate('/plans')
        ->click('nav a[href="/dashboard"]')
        ->assertPathIs('/dashboard');
});

test('authenticated user My Secrets nav link navigates to /secrets', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->click('nav a[href="/secrets"]')
        ->assertPathIs('/secrets');
});

test('authenticated user New Secret has active state on /dashboard', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->assertAttributeContains('nav a[href="/dashboard"]', 'class', 'border-gamboge-700');
});

test('authenticated user user-menu dropdown shows Profile and Settings links', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->click('[data-testid="user-menu-trigger"]');

    $page->assertSee('Profile');
    $page->assertSee('Notification Settings');
    $page->assertSee('Misc Settings');
});

test('authenticated user Profile dropdown link navigates to /user/profile', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->click('[data-testid="user-menu-trigger"]')
        ->click('Profile')
        ->assertPathIs('/user/profile');
});

test('authenticated user Notification Settings link navigates to /user/notification-settings', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->click('[data-testid="user-menu-trigger"]')
        ->click('Notification Settings')
        ->assertPathIs('/user/notification-settings');
});

test('authenticated user Misc Settings link navigates to /user/settings', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->click('[data-testid="user-menu-trigger"]')
        ->click('Misc Settings')
        ->assertPathIs('/user/settings');
});

// ─── Admin Navigation ──────────────────────────────────────────────────────────

test('admin user sees Admin dropdown in nav', function () {
    $admin = createAdminUser();
    $page = browserLogin($admin);

    $page->assertVisible('nav button[id*="admin"], nav button[aria-label*="admin"]');
})->todo('Requires a reliable selector for the Admin dropdown button — nav button role is ambiguous when multiple dropdowns exist');

test('admin user Admin dropdown shows all admin links', function () {
    $admin = createAdminUser();
    $page = browserLogin($admin);

    $page->click('nav button[id*="admin"], nav button[aria-label*="admin"]');

    $page->assertSee('Subscription Plans');
    $page->assertSee('eLocker Plans');
    $page->assertSee('Secure Line Products');
    $page->assertSee('Coupons');
    $page->assertSee('Manage Users');
})->todo('Requires a reliable selector for the Admin dropdown button — nav button role is ambiguous when multiple dropdowns exist');

test('admin Subscription Plans link navigates to /admin/plans', function () {
    $admin = createAdminUser();
    $page = browserLogin($admin);

    $page->click('nav button[id*="admin"], nav button[aria-label*="admin"]')
        ->click('Subscription Plans')
        ->assertPathIs('/admin/plans');
})->todo('Requires a reliable selector for the Admin dropdown button — nav button role is ambiguous when multiple dropdowns exist');

test('admin Manage Users link navigates to /admin/users', function () {
    $admin = createAdminUser();
    $page = browserLogin($admin);

    $page->click('nav button[id*="admin"], nav button[aria-label*="admin"]')
        ->click('Manage Users')
        ->assertPathIs('/admin/users');
})->todo('Requires a reliable selector for the Admin dropdown button — nav button role is ambiguous when multiple dropdowns exist');

test('non-admin user does not see Admin dropdown', function () {
    $user = createTestUser();
    $page = browserLogin($user);

    $page->assertDontSee('Admin');
});

// ─── Footer navigation ─────────────────────────────────────────────────────────

test('footer Blog link navigates to /blog', function () {
    visit('/')
        ->click('footer a[href="/blog"]')
        ->assertPathIs('/blog');
});

test('footer Pricing link navigates to /plans', function () {
    visit('/')
        ->click('footer a[href="/plans"]')
        ->assertPathIs('/plans');
});

test('footer Privacy Policy link navigates to /privacy-policy', function () {
    visit('/')
        ->click('footer a[href="/privacy-policy"]')
        ->assertPathIs('/privacy-policy');
});
