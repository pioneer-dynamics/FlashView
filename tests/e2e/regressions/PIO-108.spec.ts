import { test, expect } from '@playwright/test';
import { resetDatabase } from '../helpers/db';
import { createLockerCredit, createLockerViaUI, navigateToLocker } from '../helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('renew navigation from index page does not include account number in URL', async ({ page }) => {
    createLockerCredit('pio108token01', 'text', 1);
    await createLockerViaUI(page, '1081081081', 'pio108-pass-long', 'Regression 108 content', 'pio108token01');

    await page.goto('/lockers');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder(/10-digit/i).fill('1081081081');
    await page.getByRole('button', { name: 'Renew' }).click();
    await page.getByRole('button', { name: /Go to Renew/i }).click();

    await expect(page).toHaveURL(/\/lockers\/renew$/);
    expect(page.url()).not.toContain('1081081081');
});

test('renew page without sessionStorage redirects to locker index', async ({ page }) => {
    await page.goto('/lockers/renew');
    await page.waitForURL(/\/lockers\/?$/, { timeout: 10000 });

    await expect(page).toHaveURL(/\/lockers\/?$/);
});

test('renew link from open page navigates to /lockers/renew without account number in URL', async ({ page }) => {
    createLockerCredit('pio108token02', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '1082082082', 'pio108-open-renew-pass', 'Open renew regression content', 'pio108token02');

    await navigateToLocker(page, '1082082082');
    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await page.getByTestId('renew-button').click();

    await expect(page).toHaveURL(/\/lockers\/renew$/);
    expect(page.url()).not.toContain('1082082082');
});
