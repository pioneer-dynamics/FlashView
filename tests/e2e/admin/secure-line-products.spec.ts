import { test, expect } from '@playwright/test';
import { resetDatabase, createSecureLineProduct } from '../helpers/db';
import { createAdminUser, createTestUser, login } from '../helpers/auth';

test.beforeEach(() => {
    resetDatabase();
});

async function loginAsAdmin(page: Parameters<typeof login>[0]) {
    const { email, password } = createAdminUser();
    await login(page, email, password);
}

test('admin can view the Secure Line Products index page', async ({ page }) => {
    createSecureLineProduct({ name: 'Quick Call', duration_minutes: 30, amount_cents: 2000 });
    await loginAsAdmin(page);

    await page.goto('/admin/secure-line-products');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Secure Line Product Management')).toBeVisible();
    await expect(page.getByText('Quick Call')).toBeVisible();
    await expect(page.getByText('30 min')).toBeVisible();
    await expect(page.getByText('$20.00')).toBeVisible();
});

test('clicking New Product navigates to the create form', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products');

    await page.getByRole('button', { name: 'New Product' }).click();

    await expect(page).toHaveURL(/secure-line-products\/create/);
    await expect(page.getByText('New Secure Line Product')).toBeVisible();
});

test('form shows validation errors when submitted empty', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products/create');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Create Product' }).click();

    // Should stay on the form — successful create redirects to the index
    await expect(page).toHaveURL(/secure-line-products\/create/);
});

test('admin can create a product and see it listed on the index', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products/create');
    await page.waitForLoadState('networkidle');

    await page.fill('input[placeholder="30-minute Line"]', 'Premium Hour');
    await page.fill('input[type="number"][placeholder="30"]', '60');
    await page.fill('input[type="number"][placeholder="10"]', '8');
    await page.fill('input[type="number"][placeholder="20"]', '50');
    await page.fill('input[placeholder="price_1ABC..."]', 'price_test123');

    await page.getByRole('button', { name: 'Create Product' }).click();

    await expect(page).toHaveURL(/secure-line-products$/);
    await expect(page.getByText('Premium Hour')).toBeVisible();
    await expect(page.getByText('1 hr')).toBeVisible();
    await expect(page.getByText('$50.00')).toBeVisible();
    await expect(page.getByText('price_test123')).toBeVisible();
});

test('clicking Edit navigates to the edit form pre-populated with product data', async ({ page }) => {
    createSecureLineProduct({ name: 'Hour Session', duration_minutes: 60, amount_cents: 4000, stripe_price_id: 'price_abc' });
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Edit' }).first().click();

    await expect(page).toHaveURL(/secure-line-products\/\d+\/edit/);
    // Use attribute selector since getByDisplayValue is not available in Playwright 1.60
    await expect(page.locator('input[value="Hour Session"]')).toBeVisible();
    await expect(page.locator('input[value="60"]').first()).toBeVisible();
});

test('admin can update a product and see the change reflected in the table', async ({ page }) => {
    createSecureLineProduct({ name: 'Short Call', duration_minutes: 15, amount_cents: 1000, stripe_price_id: 'price_old' });
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Edit' }).first().click();
    await page.waitForLoadState('networkidle');

    // Locate by placeholder (stable); value attribute disappears after clear()
    const nameInput = page.locator('input[placeholder="30-minute Line"]');
    await expect(nameInput).toHaveValue('Short Call');
    await nameInput.clear();
    await nameInput.fill('Extended Call');

    await page.getByRole('button', { name: 'Update Product' }).click();

    await expect(page).toHaveURL(/secure-line-products$/);
    await expect(page.getByText('Extended Call')).toBeVisible();
    await expect(page.getByText('Short Call')).not.toBeVisible();
});

test('clicking Delete opens a confirmation modal; confirming removes the product', async ({ page }) => {
    createSecureLineProduct({ name: 'Call To Delete', duration_minutes: 30, amount_cents: 1500 });
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Call To Delete')).toBeVisible();

    await page.getByRole('button', { name: 'Delete' }).first().click();

    await expect(page.getByText('Delete Secure Line Product')).toBeVisible();
    // Scope to the modal dialog to avoid strict-mode collision with the table row
    await expect(page.getByRole('dialog').getByText('Call To Delete')).toBeVisible();

    await page.getByRole('button', { name: 'Delete Product' }).click();
    await page.waitForLoadState('networkidle');

    // Wait for the modal to close before asserting the row is gone
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await expect(page.locator('tbody').getByText('Call To Delete')).not.toBeVisible();
});

test('an inactive product appears visually dimmed in the table', async ({ page }) => {
    createSecureLineProduct({ name: 'Inactive Product', duration_minutes: 30, amount_cents: 1000, is_active: false });
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Inactive Product')).toBeVisible();
    // Scope "No" to the product row to avoid matching other text on the page
    const row = page.locator('tr', { has: page.getByText('Inactive Product') });
    await expect(row.getByText('No', { exact: true })).toBeVisible();

    // Row gets opacity-50 class when inactive
    await expect(row).toHaveClass(/opacity-50/);
});

test('an active product with no Stripe price shows a warning badge', async ({ page }) => {
    createSecureLineProduct({ name: 'No Price Yet', duration_minutes: 30, amount_cents: 1000, is_active: true });
    await loginAsAdmin(page);
    await page.goto('/admin/secure-line-products');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(/Active but no Stripe price/)).toBeVisible();
});

test('non-admin user is denied access to the admin products page', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.goto('/admin/secure-line-products');

    // A 403 response renders a page without the admin products table
    await expect(page.getByText('Secure Line Product Management')).not.toBeVisible();
});
