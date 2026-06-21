import { test, expect } from '@playwright/test';
import { resetDatabase } from '../helpers/db';
import { createAdminUser, createTestUser, login } from '../helpers/auth';

test.beforeEach(() => {
    resetDatabase();
});

async function loginAsAdmin(page: Parameters<typeof login>[0]) {
    const { email, password } = createAdminUser();
    await login(page, email, password);
}

test('admin can view the coupon index page with empty state', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto('/admin/coupons');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Coupon & Promotion Code Management')).toBeVisible();
    // Either shows coupons from Stripe test account or the empty state
    const emptyOrTable = page.locator('table');
    await expect(emptyOrTable).toBeVisible();
});

test('clicking New Coupon navigates to the create form', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto('/admin/coupons');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'New Coupon' }).click();

    await expect(page).toHaveURL(/coupons\/create/);
    await expect(page.getByText('New Coupon')).toBeVisible();
});

test('create form shows validation errors when submitted empty', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    // Clear required fields and submit
    await page.getByTestId('coupon-name').clear();
    await page.getByTestId('promo-code').clear();
    await page.getByTestId('discount-value').clear();

    await page.getByTestId('submit-coupon').click();

    // Should stay on the form due to validation errors
    await expect(page).toHaveURL(/coupons\/create/);
});

test('create form shows discount value label based on discount type', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    // Default is percent
    await expect(page.getByText('Discount Value (%)')).toBeVisible();

    // Switch to fixed amount
    await page.getByTestId('discount-type-amount').click();
    await expect(page.getByText('Discount Value ($ (AUD))')).toBeVisible();
    await expect(page.getByTestId('currency')).toBeVisible();
});

test('create form shows duration in months field only when repeating selected', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('duration-in-months')).not.toBeVisible();

    await page.getByTestId('duration').selectOption('repeating');

    await expect(page.getByTestId('duration-in-months')).toBeVisible();
});

test('admin can create a coupon and is redirected to the show page', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    // Use a timestamp-based code to avoid Stripe duplicate code errors across runs
    const uniqueCode = `TESTE2E${Date.now().toString().slice(-6)}`;

    await page.getByTestId('coupon-name').fill('E2E Test Coupon');
    await page.getByTestId('discount-value').fill('10');
    await page.getByTestId('promo-code').fill(uniqueCode);

    await page.getByTestId('submit-coupon').click();

    // On success, redirected to the show page
    await expect(page).toHaveURL(/coupons\/.+/);
    await expect(page).not.toHaveURL(/coupons\/create/);
    await expect(page.getByText('E2E Test Coupon')).toBeVisible();
    await expect(page.getByText(uniqueCode)).toBeVisible();
});

test('admin can toggle a promo code to inactive on the show page', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    const uniqueCode = `TOGGL${Date.now().toString().slice(-6)}`;

    await page.getByTestId('coupon-name').fill('Toggle Test Coupon');
    await page.getByTestId('discount-value').fill('5');
    await page.getByTestId('promo-code').fill(uniqueCode);

    await page.getByTestId('submit-coupon').click();
    await page.waitForURL(/coupons\/.+/);

    // Wait for the promo codes table to load
    await expect(page.getByText(uniqueCode)).toBeVisible();

    // Click Deactivate on the promo code row
    await page.getByRole('button', { name: 'Deactivate' }).first().click();
    await page.waitForLoadState('networkidle');

    // After deactivation the button changes to Activate
    await expect(page.getByRole('button', { name: 'Activate' }).first()).toBeVisible();
});

test('admin can delete a coupon after confirming the modal', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    const uniqueCode = `DELET${Date.now().toString().slice(-6)}`;

    await page.getByTestId('coupon-name').fill('Coupon To Delete');
    await page.getByTestId('discount-value').fill('15');
    await page.getByTestId('promo-code').fill(uniqueCode);

    await page.getByTestId('submit-coupon').click();
    await page.waitForURL(/coupons\/.+/);

    // Verify on show page
    await expect(page.getByText('Coupon To Delete')).toBeVisible();

    // Open delete modal
    await page.getByTestId('delete-coupon-btn').click();

    // Modal should appear with warning text
    await expect(page.getByRole('dialog').getByText(/permanently prevent ALL linked promotion codes/)).toBeVisible();

    // Confirm deletion
    await page.getByTestId('confirm-delete-coupon').click();

    // Redirected back to the index
    await expect(page).toHaveURL(/\/admin\/coupons$/);
});

test('non-admin user is denied access to admin coupon pages', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.goto('/admin/coupons');

    // A 403 response does not show the coupon management heading
    await expect(page.getByText('Coupon & Promotion Code Management')).not.toBeVisible();
});

test('back link on create form navigates to coupon index', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/coupons/create');
    await page.waitForLoadState('networkidle');

    await page.getByRole('link', { name: '← Back to Coupons' }).click();

    await expect(page).toHaveURL(/\/admin\/coupons$/);
});
