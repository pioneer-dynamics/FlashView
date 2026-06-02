/**
 * @ticket PIO-102
 * @symptom Uploading any file to eLocker fails with "Invalid array length" because
 *          encryptFileToBlob converted AES-GCM ciphertext to a hex string via
 *          Array.from().map().join(''), which exceeds V8's max string length for large files.
 *
 * Must fail before the fix (encryptFileToBlob hex path), pass after (encryptFileToBuffer).
 */

import { test, expect } from '@playwright/test';
import { resetDatabase } from '../helpers/db';
import { createLockerCredit } from '../helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('file upload does not produce Invalid array length error', async ({ page }) => {
    createLockerCredit('pio102token1', 'file', 1);

    // Collect console errors to detect the regression symptom
    const consoleErrors: string[] = [];
    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });

    // Mock the S3 prepare endpoint to avoid real S3 dependency
    await page.route('**/lockers/file/prepare', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                upload_type: 's3_direct',
                upload_url: 'https://mock-s3.example.com/locker-test.bin',
                upload_headers: { 'Content-Type': 'application/octet-stream' },
                storage_path: 'lockers/pio102-test.bin',
            }),
        });
    });

    // Mock the S3 PUT upload
    await page.route('https://mock-s3.example.com/**', async route => {
        await route.fulfill({ status: 200, body: '' });
    });

    await page.goto('/lockers/create?token=pio102token1');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill('1020304050');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-test-passphrase-long');

    // Upload a 1 KB synthetic file
    const fileContent = Buffer.alloc(1024, 0xAB);
    await page.getByLabel(/file/i).setInputFiles({
        name: 'test-regression-pio102.bin',
        mimeType: 'application/octet-stream',
        buffer: fileContent,
    });

    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    // Wait for the locker to be created (credentials panel)
    await expect(page.getByText('Locker created!')).toBeVisible({ timeout: 20000 });

    // The core regression assertion: no Invalid array length error
    const arrayLengthErrors = consoleErrors.filter(e => e.includes('Invalid array length'));
    expect(arrayLengthErrors).toHaveLength(0);
});

test('file locker creation completes and shows credentials panel', async ({ page }) => {
    createLockerCredit('pio102token2', 'file', 1);

    // Mock S3 prepare
    await page.route('**/lockers/file/prepare', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                upload_type: 's3_direct',
                upload_url: 'https://mock-s3.example.com/locker-test2.bin',
                upload_headers: { 'Content-Type': 'application/octet-stream' },
                storage_path: 'lockers/pio102-test2.bin',
            }),
        });
    });

    await page.route('https://mock-s3.example.com/**', async route => {
        await route.fulfill({ status: 200, body: '' });
    });

    await page.goto('/lockers/create?token=pio102token2');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill('5060708090');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-test-passphrase-long');

    await page.getByLabel(/file/i).setInputFiles({
        name: 'small-test-file.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('Hello, eLocker!'),
    });

    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await expect(page.getByText('Locker created!')).toBeVisible({ timeout: 20000 });
    await expect(page.getByText('Account ID', { exact: true })).toBeVisible();
    await expect(page.getByText('Passphrase', { exact: true })).toBeVisible();
});
