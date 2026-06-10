/**
 * @ticket PIO-109
 * @symptom File download fails after 15 minutes because the presigned S3 URL was
 *          generated eagerly at unlock time and cached in component state, expiring
 *          before the user clicks "Decrypt & Download".
 *
 * Must fail before the fix (download_url cached at unlock), pass after (on-demand fetch).
 */

import { test, expect } from '@playwright/test';
import { resetDatabase } from '../helpers/db';
import { createLockerCredit, createFileLockerViaUI, navigateToLocker } from '../helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('download button is visible after unlocking a file locker', async ({ page }) => {
    createLockerCredit('pio109token1', 'file', 1);
    const { accountId, passphrase } = await createFileLockerViaUI(
        page, '1091091091', 'pio109-passphrase-long', 'pio109token1', 'lockers/pio109-a.bin'
    );

    await navigateToLocker(page, accountId);
    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('download-button')).toBeVisible();
});

test('clicking download fetches a fresh URL from /download-url endpoint (not a cached URL)', async ({ page }) => {
    createLockerCredit('pio109token2', 'file', 1);
    const { accountId, passphrase } = await createFileLockerViaUI(
        page, '1092092092', 'pio109-passphrase-long', 'pio109token2', 'lockers/pio109-b.bin'
    );

    // Intercept and record calls to the on-demand download-url endpoint
    const downloadUrlRequests: string[] = [];
    await page.route('**/lockers/*/download-url', async route => {
        downloadUrlRequests.push(route.request().url());
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ download_url: 'https://mock-s3-pio109.example.com/pio109-b.bin' }),
        });
    });

    // Intercept the S3 download so the XHR resolves (avoids actual network call)
    await page.route('https://mock-s3-pio109.example.com/**', async route => {
        // Return minimal valid encrypted-file bytes — decryption will fail but we only
        // care that the /download-url request was made, not that decryption succeeds.
        await route.fulfill({ status: 200, body: Buffer.alloc(128, 0xAB) });
    });

    await navigateToLocker(page, accountId);
    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await page.getByTestId('download-button').click();

    // The key assertion: a network request was made to the /download-url endpoint
    await page.waitForFunction(() => true); // flush microtasks
    await page.waitForTimeout(500); // allow fetch to complete
    expect(downloadUrlRequests.length).toBeGreaterThan(0);
    expect(downloadUrlRequests[0]).toContain('/download-url');
});

test('download shows session-expired error message when download-url returns 403', async ({ page }) => {
    createLockerCredit('pio109token3', 'file', 1);
    const { accountId, passphrase } = await createFileLockerViaUI(
        page, '1093093093', 'pio109-passphrase-long', 'pio109token3', 'lockers/pio109-c.bin'
    );

    await navigateToLocker(page, accountId);
    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    // Mock the download-url endpoint to simulate an expired session
    await page.route('**/lockers/*/download-url', async route => {
        await route.fulfill({
            status: 403,
            contentType: 'application/json',
            body: JSON.stringify({ error: 'Invalid credentials.' }),
        });
    });

    await page.getByTestId('download-button').click();

    await expect(page.getByTestId('download-error')).toBeVisible({ timeout: 5000 });
    await expect(page.getByTestId('download-error')).toContainText(/session has expired|lock and re-open/i);
});
