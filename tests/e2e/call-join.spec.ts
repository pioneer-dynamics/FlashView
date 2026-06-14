import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createActiveCallSession, createFutureCallSession } from './helpers/calls';

test.beforeEach(() => {
    resetDatabase();
});

test('navigating to an active session renders the Join page with the bridge number', async ({ page }) => {
    const hashId = createActiveCallSession();

    await page.goto(`/calls/${hashId}`);
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(hashId)).toBeVisible();
    await expect(page.getByTestId('call-password-input')).toBeVisible();
    await expect(page.getByTestId('join-call-button')).toBeVisible();
});

test('navigating to a future session shows a "not yet started" message and a disabled button', async ({ page }) => {
    const hashId = createFutureCallSession();

    await page.goto(`/calls/${hashId}`);
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Not Yet Started')).toBeVisible();
    await expect(page.getByText(/This call starts at/i)).toBeVisible();
    // The join button should be disabled (shown as a static disabled element for not-yet-started)
    const disabledBtn = page.locator('button[disabled]:has-text("Join Call")');
    await expect(disabledBtn).toBeVisible();
});

test('navigating to an invalid bridge number returns a 404 page', async ({ page }) => {
    const response = await page.goto('/calls/invalidhash000');
    expect(response?.status()).toBe(404);
});

test('the Join Call button is disabled when password input is empty', async ({ page }) => {
    const hashId = createActiveCallSession();

    await page.goto(`/calls/${hashId}`);
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('join-call-button')).toBeDisabled();
});

test('typing in the password field enables the Join Call button', async ({ page }) => {
    const hashId = createActiveCallSession();

    await page.goto(`/calls/${hashId}`);
    await page.waitForLoadState('networkidle');

    await page.getByTestId('call-password-input').fill('any-password');
    await expect(page.getByTestId('join-call-button')).toBeEnabled();
});
