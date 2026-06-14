import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createActiveCallSession } from './helpers/calls';

test.beforeEach(() => {
    resetDatabase();
});

test('navigating to /calls renders the Secure Line index page with a bridge number input', async ({ page }) => {
    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Secure Line')).toBeVisible();
    await expect(page.getByTestId('bridge-number-input')).toBeVisible();
    await expect(page.getByTestId('join-line-button')).toBeVisible();
});

test('entering a bridge number and clicking Join Line navigates to the join page', async ({ page }) => {
    const hashId = createActiveCallSession();

    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('bridge-number-input').fill(hashId);
    await page.getByTestId('join-line-button').click();

    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(`/calls/${hashId}`);
});

test('pressing Enter in the bridge number input navigates to the join page', async ({ page }) => {
    const hashId = createActiveCallSession();

    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('bridge-number-input').fill(hashId);
    await page.getByTestId('bridge-number-input').press('Enter');

    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(`/calls/${hashId}`);
});

test('submitting an empty bridge number does not navigate', async ({ page }) => {
    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('join-line-button').click();

    await expect(page).toHaveURL('/calls');
});

test('the Join Line button is disabled when the bridge number input is empty', async ({ page }) => {
    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('join-line-button')).toBeDisabled();
});

test('the Buy a Line card is visible with a link to plans', async ({ page }) => {
    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Buy a Line')).toBeVisible();
    await expect(page.getByText('Buy a Line →')).toBeVisible();
});
