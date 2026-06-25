<?php

function adminLoginViaForm(): mixed
{
    $admin = createAdminUser();

    return browserLogin($admin);
}

test('admin can view the coupon index page with empty state', function () {
    adminLoginViaForm();

    $page = visit('/admin/coupons');

    $page->assertSee('Coupon & Promotion Code Management');
    $page->assertPresent('table');
});

test('clicking New Coupon navigates to the create form', function () {
    adminLoginViaForm();

    $page = visit('/admin/coupons');
    $page->click('New Coupon');

    $page->assertPathContains('coupons/create');
    $page->assertSee('New Coupon');
});

test('create form shows validation errors when submitted empty', function () {
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    // Clear required fields and submit
    $page->clear('[data-testid="coupon-name"]');
    $page->clear('[data-testid="promo-code"]');
    $page->clear('[data-testid="discount-value"]');

    $page->click('[data-testid="submit-coupon"]');

    // Should stay on the form due to validation errors
    $page->assertPathContains('coupons/create');
});

test('create form shows discount value label based on discount type', function () {
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    // Default is percent
    $page->assertSee('Discount Value (%)');

    // Switch to fixed amount
    $page->click('[data-testid="discount-type-amount"]');
    $page->assertSee('Discount Value ($ (AUD))');
    $page->assertVisible('[data-testid="currency"]');
});

test('create form shows duration in months field only when repeating selected', function () {
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    $page->assertMissing('[data-testid="duration-in-months"]');

    $page->select('[data-testid="duration"]', 'repeating');

    $page->assertVisible('[data-testid="duration-in-months"]');
});

test('admin can create a coupon and is redirected to the show page', function () {
    // Note: this test interacts with Stripe test mode — ensure STRIPE_SECRET is a test key
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    // Use a timestamp-based code to avoid Stripe duplicate code errors across runs
    $uniqueCode = 'TESTE2E'.substr((string) time(), -6);

    $page->fill('[data-testid="coupon-name"]', 'E2E Test Coupon');
    $page->fill('[data-testid="discount-value"]', '10');
    $page->fill('[data-testid="promo-code"]', $uniqueCode);

    $page->click('[data-testid="submit-coupon"]');

    // On success, redirected to the show page
    $page->assertPathContains('coupons/');
    $page->assertSee('E2E Test Coupon');
    $page->assertSee($uniqueCode);
});

test('admin can toggle a promo code to inactive on the show page', function () {
    // Note: this test interacts with Stripe test mode — ensure STRIPE_SECRET is a test key
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    $uniqueCode = 'TOGGL'.substr((string) time(), -6);

    $page->fill('[data-testid="coupon-name"]', 'Toggle Test Coupon');
    $page->fill('[data-testid="discount-value"]', '5');
    $page->fill('[data-testid="promo-code"]', $uniqueCode);

    $page->click('[data-testid="submit-coupon"]');

    // Wait for the promo codes table to load
    $page->assertSee($uniqueCode);

    // Click Deactivate on the promo code row
    $page->click('Deactivate');

    // After deactivation the button changes to Activate
    $page->assertSee('Activate');
});

test('admin can delete a coupon after confirming the modal', function () {
    // Note: this test interacts with Stripe test mode — ensure STRIPE_SECRET is a test key
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    $uniqueCode = 'DELET'.substr((string) time(), -6);

    $page->fill('[data-testid="coupon-name"]', 'Coupon To Delete');
    $page->fill('[data-testid="discount-value"]', '15');
    $page->fill('[data-testid="promo-code"]', $uniqueCode);

    $page->click('[data-testid="submit-coupon"]');

    // Verify on show page
    $page->assertSee('Coupon To Delete');

    // Open delete modal
    $page->click('[data-testid="delete-coupon-btn"]');

    // Modal should appear with warning text
    $page->assertSee('permanently prevent ALL linked promotion codes');

    // Confirm deletion
    $page->click('[data-testid="confirm-delete-coupon"]');

    // Redirected back to the index
    $page->assertPathIs('/admin/coupons');
});

test('non-admin user is denied access to admin coupon pages', function () {
    $user = createTestUser();
    browserLogin($user);

    $page = visit('/admin/coupons');

    // A 403 response does not show the coupon management heading
    $page->assertDontSee('Coupon & Promotion Code Management');
});

test('back link on create form navigates to coupon index', function () {
    adminLoginViaForm();

    $page = visit('/admin/coupons/create');

    $page->click('← Back to Coupons');

    $page->assertPathIs('/admin/coupons');
});
