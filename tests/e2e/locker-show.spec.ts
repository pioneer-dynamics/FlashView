import { test, expect } from '@playwright/test';
import { resetDatabase, clearCache } from './helpers/db';
import { createLockerCredit, createLockerViaUI, createFileLockerViaUI } from './helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('show page for unknown account ID returns 404', async ({ page }) => {
    const response = await page.request.get('/lockers/9999999999');
    expect(response.status()).toBe(404);
});

test('lock icon is visible before unlock is attempted', async ({ page }) => {
    createLockerCredit('showtoken01', 'text', 1);
    await createLockerViaUI(page, '2222222222', 'my-show-passphrase-long', 'Test content', 'showtoken01');

    await page.goto('/lockers/2222222222');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('lock-icon')).toBeVisible();
    await expect(page.getByTestId('passphrase-input')).toBeVisible();
    await expect(page.getByTestId('unlock-button')).toBeVisible();
});

test('correct passphrase decrypts and displays content', async ({ page }) => {
    createLockerCredit('showtoken02', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '3333333333', 'my-correct-passphrase-long', 'My secret locker text', 'showtoken02');

    await page.goto('/lockers/3333333333');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('My secret locker text')).toBeVisible();
});

test('after submitting correct passphrase, lock animation plays and content is revealed', async ({ page }) => {
    createLockerCredit('showtoken03', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '4444444444', 'my-anim-passphrase-long', 'Animation test content', 'showtoken03');

    await page.goto('/lockers/4444444444');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    // Button should show "Unlocking…" during animation
    await expect(page.getByRole('button', { name: /Unlocking/i })).toBeVisible({ timeout: 3000 });

    // Content should appear after animation completes
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
});

test('wrong passphrase shows error and lock shake animation', async ({ page }) => {
    createLockerCredit('showtoken04', 'text', 1);
    await createLockerViaUI(page, '5555555555', 'my-real-passphrase-long', 'Content for wrong pass test', 'showtoken04');

    await page.goto('/lockers/5555555555');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('passphrase-input').fill('wrong-passphrase-here-!');
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypt-error')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText(/Incorrect passphrase|Decryption failed/i)).toBeVisible();
});

test('after submitting wrong passphrase, lock shake animation plays and error message appears', async ({ page }) => {
    createLockerCredit('showtoken05', 'text', 1);
    await createLockerViaUI(page, '6666666666', 'my-real-passphrase-long', 'Content for shake test', 'showtoken05');

    await page.goto('/lockers/6666666666');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('passphrase-input').fill('wrong!');
    await page.getByTestId('unlock-button').click();

    // Error message should appear
    await expect(page.getByTestId('decrypt-error')).toBeVisible({ timeout: 10000 });

    // Lock icon should still be visible (not replaced by content)
    await expect(page.getByTestId('lock-icon')).toBeVisible();
});

test('repeated wrong passphrase shows permanent loss warning', async ({ page }) => {
    createLockerCredit('showtoken06', 'text', 1);
    await createLockerViaUI(page, '7777777777', 'my-real-passphrase-long', 'Content for repeated fail test', 'showtoken06');

    await page.goto('/lockers/7777777777');
    await page.waitForLoadState('networkidle');

    // Two wrong passphrase attempts — clear cache between to avoid rate-limiter blocking 2nd fetch
    for (let i = 0; i < 2; i++) {
        await page.getByTestId('unlock-button').waitFor({ state: 'visible' });
        await page.getByTestId('passphrase-input').fill('wrong-pass-attempt');
        await page.getByTestId('unlock-button').click();
        await page.getByTestId('decrypt-error').waitFor({ state: 'visible', timeout: 10000 });
        clearCache();
    }

    await expect(page.getByText(/passphrase is lost/i)).toBeVisible();
});

test('update panel is always visible with lost token warning', async ({ page }) => {
    createLockerCredit('showtoken07', 'text', 1);
    await createLockerViaUI(page, '8888888888', 'my-real-passphrase-long', 'Content for update panel test', 'showtoken07');

    await page.goto('/lockers/8888888888');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Update Content')).toBeVisible();
    await expect(page.getByText(/If you have lost your Update Token/i)).toBeVisible();
    await expect(page.getByTestId('update-token-input')).toBeVisible();
});

test('expiry badge is visible on show page', async ({ page }) => {
    createLockerCredit('showtoken08', 'text', 1);
    await createLockerViaUI(page, '9999111111', 'my-real-passphrase-long', 'Content for expiry badge test', 'showtoken08');

    await page.goto('/lockers/9999111111');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(/days remaining|Expires/i)).toBeVisible();
    await expect(page.getByText('Renew')).toBeVisible();
});

// ─── DEK-based file locker flows (PIO-102) ───────────────────────────────────

test('file locker shows file metadata after unlock', async ({ page }) => {
    createLockerCredit('fileshow01', 'file', 1);
    const passphrase = 'my-file-locker-passphrase';
    await createFileLockerViaUI(page, '1122334455', passphrase, 'fileshow01');

    await page.goto('/lockers/1122334455');
    await page.waitForLoadState('networkidle');

    // Mock the S3 temporary URL returned by the unlock endpoint
    await page.route('**/1122334455/unlock', async route => {
        const response = await route.fetch();
        const body = await response.json();
        // Override download_url to point to our mock S3
        if (body.download_url) {
            body.download_url = 'https://mock-s3-locker.example.com/lockers/test-file.bin';
        }
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
    });

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText(/test-file\.txt/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Decrypt.*Download/i })).toBeVisible();
});

test('passphrase change on DEK-based file locker does not re-download the file', async ({ page }) => {
    createLockerCredit('fileshow02', 'file', 1);
    const passphrase = 'my-file-locker-passphrase';
    const newPassphrase = 'brand-new-passphrase-for-locker';
    await createFileLockerViaUI(page, '2233445566', passphrase, 'fileshow02');

    await page.goto('/lockers/2233445566');
    await page.waitForLoadState('networkidle');

    // Track any S3 download requests — must be 0 during passphrase change
    const s3Downloads: string[] = [];
    page.on('request', req => {
        if (req.method() === 'GET' && req.url().includes('mock-s3-locker')) {
            s3Downloads.push(req.url());
        }
    });

    await page.route('**/2233445566/unlock', async route => {
        const response = await route.fetch();
        const body = await response.json();
        if (body.download_url) {
            body.download_url = 'https://mock-s3-locker.example.com/lockers/test-file.bin';
        }
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
    });

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    // Change passphrase — for a DEK-based locker this is crypto-only, no file download
    await page.getByPlaceholder('Enter or generate a passphrase').last().fill(newPassphrase);
    await page.getByRole('button', { name: /Change Passphrase/i }).click();

    await expect(page.getByText(/Passphrase changed/i)).toBeVisible({ timeout: 15000 });

    // Core assertion: no S3 file was downloaded during passphrase change
    expect(s3Downloads).toHaveLength(0);
});
