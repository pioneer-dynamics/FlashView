import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import {
    createLockerCredit,
    createLockerViaUI,
    createKeyFileLockerViaUI,
    createCombinedLockerViaUI,
    navigateToLocker,
    navigateToLockerRenew,
    KEY_FILE_ALPHA,
} from './helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('renew link on open page leads to renew page without account number in URL', async ({ page }) => {
    createLockerCredit('renewtoken01', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '1010101010', 'renew-test-passphrase-long', 'Content for renew test', 'renewtoken01');

    await navigateToLocker(page, '1010101010');
    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await page.getByText('Renew').click();

    await expect(page).toHaveURL(/\/lockers\/renew$/);
    await expect(page.getByText(/Renew eLocker/i)).toBeVisible();
    await expect(page.getByTestId('passphrase-input')).toBeVisible();
});

test('renew page displays current tier', async ({ page }) => {
    createLockerCredit('renewtoken02', 'text', 1);
    await createLockerViaUI(page, '2020202020', 'renew-test-passphrase-long', 'Content for tier display test', 'renewtoken02');

    await navigateToLockerRenew(page, '2020202020');

    await expect(page.getByText(/Text Locker|text locker/i)).toBeVisible();
});

test('renew page shows duration selection buttons', async ({ page }) => {
    createLockerCredit('renewtoken03', 'text', 1);
    await createLockerViaUI(page, '3030303030', 'renew-test-passphrase-long', 'Content for duration test', 'renewtoken03');

    await navigateToLockerRenew(page, '3030303030');

    await expect(page.getByRole('button', { name: '1yr' })).toBeVisible();
    await expect(page.getByRole('button', { name: '3yr' })).toBeVisible();
    await expect(page.getByRole('button', { name: '5yr' })).toBeVisible();
});

test('wrong passphrase on renew shows error', async ({ page }) => {
    createLockerCredit('renewtoken04', 'text', 1);
    await createLockerViaUI(page, '4040404040', 'renew-test-passphrase-long', 'Content for wrong pass test', 'renewtoken04');

    await navigateToLockerRenew(page, '4040404040');

    await page.getByTestId('passphrase-input').fill('wrong-passphrase-!');
    await page.getByTestId('renew-submit-button').click();

    await expect(page.getByText(/Invalid passphrase|Authentication failed/i)).toBeVisible({ timeout: 10000 });
});

// ─── Key-file renewal ─────────────────────────────────────────────────────────

test('key-file locker renew page shows key file inputs (not passphrase)', async ({ page }) => {
    createLockerCredit('renewtoken05', 'text', 1);
    await createKeyFileLockerViaUI(page, '5050505050', 'Key-file renew content', 'renewtoken05', [KEY_FILE_ALPHA]);

    await navigateToLockerRenew(page, '5050505050');

    await expect(page.getByTestId('key-file-input-label')).toBeVisible({ timeout: 5000 });
    await expect(page.getByTestId('passphrase-input')).not.toBeVisible();
});

test('combined locker renew page shows both passphrase and key file inputs', async ({ page }) => {
    createLockerCredit('renewtoken06', 'text', 1);
    await createCombinedLockerViaUI(page, '6060606060', 'combined-renew-pass', 'Combined renew content', 'renewtoken06', [KEY_FILE_ALPHA]);

    await navigateToLockerRenew(page, '6060606060');

    await expect(page.getByTestId('passphrase-input')).toBeVisible({ timeout: 5000 });
    await expect(page.getByTestId('key-file-input-label')).toBeVisible();
});

// ─── ECDSA passphrase renewal (PIO-103) ───────────────────────────────────────

test('ECDSA locker renewal with correct passphrase redirects to Stripe checkout', async ({ page }) => {
    createLockerCredit('ecdsarenew01', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '7070707070', 'ecdsa-renew-passphrase-long', 'Renewal test content', 'ecdsarenew01');

    // Mock Stripe checkout creation — Stripe is not available in test environment
    await page.route('**/7070707070/renew', async (route, request) => {
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

    await navigateToLockerRenew(page, '7070707070');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('renew-submit-button').click();

    // Should redirect to Stripe checkout URL
    await expect(page).toHaveURL(/checkout\.stripe\.com/, { timeout: 15000 });
});

test('ECDSA locker renewal with wrong passphrase shows error', async ({ page }) => {
    createLockerCredit('ecdsarenew02', 'text', 1);
    await createLockerViaUI(page, '8080808080', 'ecdsa-renew-correct-pass', 'Renewal wrong pass test', 'ecdsarenew02');

    await navigateToLockerRenew(page, '8080808080');

    await page.getByTestId('passphrase-input').fill('totally-wrong-passphrase!');
    await page.getByTestId('renew-submit-button').click();

    await expect(page.getByText(/Invalid passphrase/i)).toBeVisible({ timeout: 10000 });
});

// ─── Direct navigation (no sessionStorage) ───────────────────────────────────

test('renew page without sessionStorage redirects to locker index', async ({ page }) => {
    // Navigate directly without setting sessionStorage — must redirect to /lockers
    await page.goto('/lockers/renew');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/lockers\/?$/);
});
