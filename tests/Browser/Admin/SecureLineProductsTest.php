<?php

test('admin can view the Secure Line Products index page', function () {
    createSecureLineProduct(['name' => 'Quick Call', 'duration_minutes' => 30, 'amount_cents' => 2000]);
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');

    $page->assertSee('Secure Line Product Management');
    $page->assertSee('Quick Call');
    $page->assertSee('30 min');
    $page->assertSee('$20.00');
});

test('clicking New Product navigates to the create form', function () {
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');
    $page->click('New Product');

    $page->assertPathContains('secure-line-products/create');
    $page->assertSee('New Secure Line Product');
});

test('form shows validation errors when submitted empty', function () {
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products/create');

    $page->click('Create Product');

    // Should stay on the form — successful create redirects to the index
    $page->assertPathContains('secure-line-products/create');
});

test('admin can create a product and see it listed on the index', function () {
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products/create');

    $page->fill('input[placeholder="30-minute Line"]', 'Premium Hour');
    $page->fill('input[type="number"][placeholder="30"]', '60');
    $page->fill('input[type="number"][placeholder="10"]', '8');
    $page->fill('input[type="number"][placeholder="20"]', '50');
    $page->fill('input[placeholder="price_1ABC..."]', 'price_test123');

    $page->click('Create Product');

    $page->assertPathContains('secure-line-products');
    $page->assertSee('Premium Hour');
    $page->assertSee('1 hr');
    $page->assertSee('$50.00');
    $page->assertSee('price_test123');
});

test('clicking Edit navigates to the edit form pre-populated with product data', function () {
    createSecureLineProduct(['name' => 'Hour Session', 'duration_minutes' => 60, 'amount_cents' => 4000, 'stripe_price_id' => 'price_abc']);
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');
    $page->click('Edit');

    $page->assertPathContains('secure-line-products');
    $page->assertPathContains('edit');
    $page->assertVisible('input[value="Hour Session"]');
    $page->assertVisible('input[value="60"]');
});

test('admin can update a product and see the change reflected in the table', function () {
    createSecureLineProduct(['name' => 'Short Call', 'duration_minutes' => 15, 'amount_cents' => 1000, 'stripe_price_id' => 'price_old']);
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');
    $page->click('Edit');

    // Clear the name input and fill with new value
    $page->clear('input[placeholder="30-minute Line"]');
    $page->fill('input[placeholder="30-minute Line"]', 'Extended Call');

    $page->click('Update Product');

    $page->assertPathContains('secure-line-products');
    $page->assertSee('Extended Call');
    $page->assertDontSee('Short Call');
});

test('clicking Delete opens a confirmation modal; confirming removes the product', function () {
    createSecureLineProduct(['name' => 'Call To Delete', 'duration_minutes' => 30, 'amount_cents' => 1500]);
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');

    $page->assertSee('Call To Delete');

    $page->click('Delete');

    $page->assertSee('Delete Secure Line Product');
    $page->assertSee('Call To Delete');

    $page->click('Delete Product');

    $page->assertDontSee('Call To Delete');
});

test('an inactive product appears visually dimmed in the table', function () {
    createSecureLineProduct(['name' => 'Inactive Product', 'duration_minutes' => 30, 'amount_cents' => 1000, 'is_active' => false]);
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');

    $page->assertSee('Inactive Product');
    // Row gets opacity-50 class when inactive
    $page->assertAttributeContains('tr:has-text("Inactive Product")', 'class', 'opacity-50');
});

test('an active product with no Stripe price shows a warning badge', function () {
    createSecureLineProduct(['name' => 'No Price Yet', 'duration_minutes' => 30, 'amount_cents' => 1000, 'is_active' => true]);
    $admin = createAdminUser();
    browserLogin($admin);

    $page = visit('/admin/secure-line-products');

    $page->assertSee('Active but no Stripe price');
});

test('non-admin user is denied access to the admin products page', function () {
    $user = createTestUser();
    browserLogin($user);

    $page = visit('/admin/secure-line-products');

    // A 403 response renders a page without the admin products table
    $page->assertDontSee('Secure Line Product Management');
});
