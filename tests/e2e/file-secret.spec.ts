import { test, expect } from '@playwright/test';
import { resetDatabase, seedPlans } from './helpers/db';
import { createTestUser, login } from './helpers/auth';

// File upload requires an authenticated user with a plan that permits uploads.
// seedPlans() creates the Free plan which allows up to 10 MB.
test.beforeEach(() => {
    resetDatabase();
    seedPlans();
});

test('authenticated user creates a file secret and recipient downloads it', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.goto('/');

    // Upload a small plain-text file via the hidden file input in FileUploadZone.
    await page.getByTestId('file-input').setInputFiles({
        name: 'test-document.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('Hello from the E2E file secret test'),
    });

    await page.click('button:has-text("Generate link")');

    // Encryption + server-side upload can take a few seconds.
    await expect(page.locator('text=The file can be downloaded only once')).toBeVisible({ timeout: 30000 });

    const shareUrl = await page.getByTestId('share-url').locator('code').innerText();
    const passphrase = await page.getByTestId('passphrase').locator('code').innerText();

    // Visit as recipient and download.
    await page.goto(shareUrl.trim());
    await page.fill('#password', passphrase.trim());

    const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 30000 }),
        page.click('button:has-text("Download and decrypt")'),
    ]);

    expect(download.suggestedFilename()).toBe('test-document.txt');
});

test('file secret is inaccessible after download', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.goto('/');

    await page.getByTestId('file-input').setInputFiles({
        name: 'burn-after-read.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('This file self-destructs'),
    });

    await page.click('button:has-text("Generate link")');
    await expect(page.locator('text=The file can be downloaded only once')).toBeVisible({ timeout: 30000 });

    const shareUrl = await page.getByTestId('share-url').locator('code').innerText();
    const passphrase = await page.getByTestId('passphrase').locator('code').innerText();

    // First download.
    await page.goto(shareUrl.trim());
    await page.fill('#password', passphrase.trim());
    const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 30000 }),
        page.click('button:has-text("Download and decrypt")'),
    ]);
    expect(download.suggestedFilename()).toBe('burn-after-read.txt');

    // Second attempt — file is deleted after download; filepath is null so button now says "Retrieve Message".
    await page.goto(shareUrl.trim());
    await page.fill('#password', passphrase.trim());
    await page.click('button:has-text("Retrieve")');
    await expect(page.getByTestId('destroyed-state')).toBeVisible();
});
