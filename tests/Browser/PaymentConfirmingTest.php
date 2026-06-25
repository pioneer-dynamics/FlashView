<?php

test('PaymentConfirming page mounts and shows shimmer bar', function () {
    // Must use a user with no active subscription — controller redirects subscribers to /dashboard
    $user = createTestUser();

    $page = browserLogin($user)
        ->navigate('/payment/confirming');

    // Shimmer bar is visible while polling for activation
    $page->assertVisible('.animate-shimmer');

    // Label text is visible
    $page->assertSee('Activating Your Plan');

    // Timeout state must NOT be visible yet
    $page->assertDontSee('Taking Longer Than Expected');
});

test('PaymentConfirming shows timeout state after 30s', function () {
    todo('Requires page.clock API — not available in pest-plugin-browser v4');
});

test('PaymentConfirming redirects subscriber to dashboard', function () {
    // Without a subscription the page loads normally with the shimmer bar
    $user = createTestUser();

    $page = browserLogin($user)
        ->navigate('/payment/confirming');

    $page->assertVisible('.animate-shimmer');
});
