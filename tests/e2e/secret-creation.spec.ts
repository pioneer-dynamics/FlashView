import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';

test.beforeEach(async () => {
    resetDatabase();
});

test('guest creates a text secret and receives a share link', async ({ page }) => {
    await page.goto('/');
    await page.fill('#message', 'My secret message');
    await page.click('button:has-text("Generate link")');

    await expect(page.locator('text=Please share the link and password separately')).toBeVisible();
    await expect(page.getByTestId('share-url')).toBeVisible();
    await expect(page.getByTestId('passphrase')).toBeVisible();
});

test('guest creates a secret with a custom password', async ({ page }) => {
    await page.goto('/');
    await page.fill('#message', 'Secret with custom password');
    await page.fill('#password', 'my-custom-password');
    await page.click('button:has-text("Generate link")');

    await expect(page.locator('text=Please share the link and password separately')).toBeVisible();
    await expect(page.getByTestId('share-url')).toBeVisible();
});
