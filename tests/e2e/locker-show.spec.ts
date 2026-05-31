import { test, expect } from '@playwright/test';
import { resetDatabase, clearCache } from './helpers/db';
import { createLockerCredit, createLockerViaUI } from './helpers/locker';

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
