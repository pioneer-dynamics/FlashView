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

// ─── ECDSA renewal (PIO-103) ──────────────────────────────────────────────────

test('ECDSA locker renewal with correct passphrase redirects to Stripe checkout', async ({ page }) => {
    createLockerCredit('ecdsarenew01', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '6060606060', 'ecdsa-renew-passphrase-long', 'Renewal test content', 'ecdsarenew01');

    // Mock Stripe checkout creation — Stripe is not available in test environment
    await page.route('**/6060606060/renew', async (route, request) => {
        if (request.method() === 'POST') {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ checkout_url: 'https://checkout.stripe.com/test-session' }),
            });
        } else {
            await route.continue();
        }
    });

    await page.goto('/lockers/6060606060/renew');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder(/passphrase/i).fill(passphrase);
    await page.getByRole('button', { name: /Renew for/i }).click();

    // Should redirect to Stripe checkout URL
    await expect(page).toHaveURL(/checkout\.stripe\.com/, { timeout: 15000 });
});

test('ECDSA locker renewal with wrong passphrase shows error', async ({ page }) => {
    createLockerCredit('ecdsarenew02', 'text', 1);
    await createLockerViaUI(page, '7070707070', 'ecdsa-renew-correct-pass', 'Renewal wrong pass test', 'ecdsarenew02');

    await page.goto('/lockers/7070707070/renew');
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder(/passphrase/i).fill('totally-wrong-passphrase!');
    await page.getByRole('button', { name: /Renew for/i }).click();

    await expect(page.getByText(/Invalid passphrase/i)).toBeVisible({ timeout: 10000 });
});
