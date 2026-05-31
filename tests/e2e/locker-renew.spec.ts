import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createLockerCredit, createLockerViaUI } from './helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('renew page loads from show page expiry badge link', async ({ page }) => {
    createLockerCredit('renewtoken01', 'text', 1);
    await createLockerViaUI(page, '1010101010', 'renew-test-passphrase-long', 'Content for renew test', 'renewtoken01');

    await page.goto('/lockers/1010101010');
    await page.waitForLoadState('networkidle');

    await page.getByText('Renew').click();

    await expect(page).toHaveURL(/\/lockers\/1010101010\/renew/);
    await expect(page.getByText(/Renew eLocker/i)).toBeVisible();
    await expect(page.getByPlaceholder(/passphrase/i)).toBeVisible();
});

test('renew page displays current tier', async ({ page }) => {
    createLockerCredit('renewtoken02', 'text', 1);
    await createLockerViaUI(page, '2020202020', 'renew-test-passphrase-long', 'Content for tier display test', 'renewtoken02');

    await page.goto('/lockers/2020202020/renew');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(/Text Locker|text locker/i)).toBeVisible();
});

test('renew page shows duration selection buttons', async ({ page }) => {
    createLockerCredit('renewtoken03', 'text', 1);
    await createLockerViaUI(page, '3030303030', 'renew-test-passphrase-long', 'Content for duration test', 'renewtoken03');

    await page.goto('/lockers/3030303030/renew');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('button', { name: '1yr' })).toBeVisible();
    await expect(page.getByRole('button', { name: '3yr' })).toBeVisible();
    await expect(page.getByRole('button', { name: '5yr' })).toBeVisible();
});

test('wrong passphrase on renew shows error', async ({ page }) => {
    createLockerCredit('renewtoken04', 'text', 1);
    await createLockerViaUI(page, '4040404040', 'renew-test-passphrase-long', 'Content for wrong pass test', 'renewtoken04');

    await page.goto('/lockers/4040404040/renew');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder(/passphrase/i).fill('wrong-passphrase-!');
    await page.getByRole('button', { name: /Renew for/i }).click();

    await expect(page.getByText(/Invalid passphrase|Authentication failed/i)).toBeVisible({ timeout: 10000 });
});
