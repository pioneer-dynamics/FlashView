import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createLockerCredit } from './helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('create page shows 404 for unknown credit token', async ({ page }) => {
    await page.goto('/lockers/create?token=unknowntoken');

    await expect(page).toHaveURL(/\/lockers\/create/);
    // Should get a 404 response
    const response = await page.request.get('/lockers/create?token=unknowntoken');
    expect(response.status()).toBe(404);
});

test('create page loads with a valid unused credit token', async ({ page }) => {
    createLockerCredit('validtoken123', 'text', 1);

    await page.goto('/lockers/create?token=validtoken123');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Create your eLocker')).toBeVisible();
    await expect(page.getByPlaceholder('Choose a 10-digit number')).toBeVisible();
    await expect(page.getByPlaceholder('Enter or generate a passphrase')).toBeVisible();
});

test('account ID must be exactly 10 digits', async ({ page }) => {
    createLockerCredit('validtoken456', 'text', 1);

    await page.goto('/lockers/create?token=validtoken456');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill('12345');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('test-passphrase-long-enough');
    await page.getByPlaceholder('Enter the content to store…').fill('test content');
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await expect(page.getByText('Account ID must be exactly 10 digits.')).toBeVisible();
});

test('account ID must contain only digits', async ({ page }) => {
    createLockerCredit('validtoken789', 'text', 1);

    await page.goto('/lockers/create?token=validtoken789');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill('abcdefghij');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('test-passphrase-long-enough');
    await page.getByPlaceholder('Enter the content to store…').fill('test content');
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await expect(page.getByText('Account ID must be exactly 10 digits.')).toBeVisible();
});

test('generate button fills passphrase field', async ({ page }) => {
    createLockerCredit('validtokenabc', 'text', 1);

    await page.goto('/lockers/create?token=validtokenabc');
    await page.waitForLoadState('networkidle');

    const passphraseInput = page.getByPlaceholder('Enter or generate a passphrase');
    await expect(passphraseInput).toHaveValue('');

    await page.getByRole('button', { name: 'Generate' }).click();

    const value = await passphraseInput.inputValue();
    expect(value.length).toBeGreaterThan(10);
});

test('ECDSA locker creation shows credentials panel with account ID and passphrase', async ({ page }) => {
    createLockerCredit('createtoken01', 'text', 1);

    await page.goto('/lockers/create?token=createtoken01');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill('1234567890');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-strong-test-passphrase-here');
    await page.getByPlaceholder('Enter the content to store…').fill('Secret text content');
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await expect(page.getByText('Locker created!')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Account ID', { exact: true })).toBeVisible();
    await expect(page.getByText('Passphrase', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Download as text file/i })).toBeVisible();
});

test('duplicate account ID shows validation error', async ({ page }) => {
    createLockerCredit('createtoken02', 'text', 1);
    createLockerCredit('createtoken03', 'text', 1);

    // Create first locker
    await page.goto('/lockers/create?token=createtoken02');
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder('Choose a 10-digit number').fill('9876543210');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-strong-test-passphrase-here');
    await page.getByPlaceholder('Enter the content to store…').fill('First locker content');
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();
    await page.waitForSelector('text=Locker created!', { timeout: 15000 });

    // Attempt to create second locker with the same ID
    await page.goto('/lockers/create?token=createtoken03');
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder('Choose a 10-digit number').fill('9876543210');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-strong-test-passphrase-here');
    await page.getByPlaceholder('Enter the content to store…').fill('Second locker content');
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await expect(page.getByText(/taken|already/i)).toBeVisible({ timeout: 10000 });
});

test('file locker creation with DEK envelope encryption completes without error', async ({ page }) => {
    createLockerCredit('filetoken01', 'file', 1);

    // Mock S3 endpoints (not available in test environment)
    await page.route('**/lockers/file/prepare', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                upload_type: 's3_direct',
                upload_url: 'https://mock-s3.example.com/test-locker.bin',
                upload_headers: { 'Content-Type': 'application/octet-stream' },
                storage_path: 'lockers/test-locker.bin',
            }),
        });
    });
    await page.route('https://mock-s3.example.com/**', async route => {
        await route.fulfill({ status: 200, body: '' });
    });

    await page.goto('/lockers/create?token=filetoken01');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill('1020304050');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-strong-test-passphrase-here');

    await page.getByLabel(/file/i).setInputFiles({
        name: 'test-file.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('file locker test content'),
    });

    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await expect(page.getByText('Locker created!')).toBeVisible({ timeout: 20000 });
    await expect(page.getByText('Account ID', { exact: true })).toBeVisible();
});

test('credentials panel has download button and confirmation checkbox', async ({ page }) => {
    createLockerCredit('createtoken04', 'text', 1);

    await page.goto('/lockers/create?token=createtoken04');
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder('Choose a 10-digit number').fill('1111111111');
    await page.getByPlaceholder('Enter or generate a passphrase').fill('my-strong-test-passphrase-here');
    await page.getByPlaceholder('Enter the content to store…').fill('Content for credentials test');
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    await page.waitForSelector('text=Locker created!', { timeout: 15000 });

    // Open locker button should be disabled until checkbox checked
    const openButton = page.getByRole('button', { name: /Open my locker/i });
    await expect(openButton).toBeDisabled();

    // Check the confirmation checkbox
    await page.getByLabel(/I have saved both credentials/i).check();
    await expect(openButton).toBeEnabled();
});
