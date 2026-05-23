import { test, expect } from '@playwright/test';
import { resetDatabase, expireAllSecrets } from './helpers/db';
import { createSecret } from './helpers/secrets';

test.beforeEach(async () => {
    resetDatabase();
});

test('recipient visits share link, enters passphrase, and decrypts message', async ({ page }) => {
    const message = 'Hello from E2E test';
    const { shareUrl, passphrase } = await createSecret(page, message);

    await page.goto(shareUrl);
    await page.fill('#password', passphrase);
    await page.click('button:has-text("Retrieve")');

    await expect(page.locator('text=' + message)).toBeVisible();
});

test('secret is inaccessible after first retrieval (burn-after-reading)', async ({ page }) => {
    const message = 'One-time message';
    const { shareUrl, passphrase } = await createSecret(page, message);

    // First retrieval
    await page.goto(shareUrl);
    await page.fill('#password', passphrase);
    await page.click('button:has-text("Retrieve")');
    await expect(page.locator('text=' + message)).toBeVisible();

    // Second visit — message is null in DB; clicking Retrieve returns empty flash → destroyed state.
    await page.goto(shareUrl);
    await page.fill('#password', passphrase);
    await page.click('button:has-text("Retrieve")');
    await expect(page.getByTestId('destroyed-state')).toBeVisible();
});

test('expired secret shows appropriate error state', async ({ page }) => {
    const { shareUrl, passphrase } = await createSecret(page, 'Expiring secret');

    // Expire all secrets via tinker — there is no artisan command for this.
    // Sets expires_at = now()-1m and message = null, replicating what ClearExpiredSecrets job does.
    expireAllSecrets();

    // The share URL still renders the view — destroyed state only appears after clicking Retrieve.
    await page.goto(shareUrl);
    await page.fill('#password', passphrase);
    await page.click('button:has-text("Retrieve")');
    await expect(page.getByTestId('destroyed-state')).toBeVisible();
});
