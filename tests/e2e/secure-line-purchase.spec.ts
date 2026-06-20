import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createSecureLineProduct } from './helpers/db';
import { createSecureLineCredit } from './helpers/calls';

test.beforeEach(() => {
    resetDatabase();
});

// ── Call/Index buy button ────────────────────────────────────────────────────

test('Buy a Line card links to /calls/buy', async ({ page }) => {
    await page.goto('/calls');
    await page.waitForLoadState('networkidle');

    const buyLink = page.getByText('Buy a Line →');
    await expect(buyLink).toBeVisible();

    await buyLink.click();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL('/calls/buy');
});

// ── Buy page ─────────────────────────────────────────────────────────────────

test('buy page shows "How it works" block and active products', async ({ page }) => {
    createSecureLineProduct({ name: 'Quick Call', amount_cents: 2000, stripe_price_id: 'price_test_e2e' });

    await page.goto('/calls/buy');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('How it works')).toBeVisible();
    await expect(page.getByText('Pay once — no account, no subscription.')).toBeVisible();
    await expect(page.getByText('Receive a bridge number and a call password.')).toBeVisible();
    await expect(page.getByText(/Your call window starts the moment/)).toBeVisible();

    await expect(page.getByText('Quick Call')).toBeVisible();
    await expect(page.getByTestId('product-price')).toContainText('$20');
    await expect(page.getByTestId('purchase-button')).toBeVisible();
});

test('buy page shows empty state when no products', async ({ page }) => {
    await page.goto('/calls/buy');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('No Secure Line products are currently available.')).toBeVisible();
});

test('buy page excludes inactive products', async ({ page }) => {
    createSecureLineProduct({ name: 'Hidden Product', stripe_price_id: 'price_test_e2e', is_active: false });

    await page.goto('/calls/buy');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Hidden Product')).not.toBeVisible();
});

// ── Pending token recovery banner ────────────────────────────────────────────

test('buy page shows recovery banner when pending token in localStorage', async ({ page }) => {
    // Navigate first to set localStorage, then revisit
    await page.goto('/calls/buy');
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => {
        localStorage.setItem('secure_line_pending_token', 'recovery-test-token');
    });

    // Reload to trigger the onMounted check
    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Unused Secure Line Credit')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue →' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Dismiss' })).toBeVisible();
});

test('dismissing the recovery banner removes it', async ({ page }) => {
    await page.goto('/calls/buy');
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => {
        localStorage.setItem('secure_line_pending_token', 'dismiss-test-token');
    });

    await page.reload();
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Dismiss' }).click();

    await expect(page.getByText('Unused Secure Line Credit')).not.toBeVisible();
});

// ── AwaitCredit page ─────────────────────────────────────────────────────────

test('await-credit page shows shimmer and payment reference initially', async ({ page }) => {
    await page.goto('/calls/await-credit?session=cs_test_e2e_fake');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Confirming your payment…')).toBeVisible();
    await expect(page.getByText('cs_test_e2e_fake')).toBeVisible();
    await expect(page.getByText('Waiting for Stripe confirmation…')).toBeVisible();
});

test('await-credit shows retry button after timeout', async ({ page }) => {
    // Block the credit-status endpoint so it never resolves
    await page.route('**/calls/credit-status**', route =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ pending: true }) })
    );

    // Install fake clock before navigation so setInterval is captured
    await page.clock.install();
    await page.goto('/calls/await-credit?session=cs_test_timeout');
    await page.waitForLoadState('networkidle');

    // Step through 32 intervals of 2s each so Vue flushes reactivity between ticks
    for (let i = 0; i < 32; i++) {
        await page.clock.runFor(2000);
    }

    await expect(page.getByRole('button', { name: 'Try again' })).toBeVisible({ timeout: 5000 });
    await expect(page.getByText('Bank transfers may take 1–3 business days')).toBeVisible();
});

// ── Create page ───────────────────────────────────────────────────────────────

test('create page shows 404 for invalid token', async ({ page }) => {
    const response = await page.request.get('/calls/create?token=notexists');
    expect(response.status()).toBe(404);
});

test('create page shows 404 for used credit token', async ({ page }) => {
    createSecureLineCredit('usedtoken123', true);

    const response = await page.request.get('/calls/create?token=usedtoken123');
    expect(response.status()).toBe(404);
});

test('full happy path: create page generates credentials and shows them', async ({ page }) => {
    createSecureLineCredit('e2etoken123');

    await page.goto('/calls/create?token=e2etoken123');
    await page.waitForLoadState('networkidle');

    // Wait for credentials panel to appear (crypto + API call)
    await expect(page.getByTestId('bridge-number')).toBeVisible({ timeout: 15000 });

    // Bridge number and password are shown
    const bridgeNumber = await page.getByTestId('bridge-number').textContent();
    expect(bridgeNumber?.trim().length).toBeGreaterThan(0);

    await expect(page.getByTestId('call-password')).toBeVisible();
    const password = await page.getByTestId('call-password').textContent();
    expect(password?.trim().length).toBeGreaterThan(0);

    // Session expiry is shown
    await expect(page.getByTestId('session-expiry')).toBeVisible();

    // Copy buttons present
    await expect(page.getByTestId('copy-bridge-number')).toBeVisible();
    await expect(page.getByTestId('copy-call-password')).toBeVisible();

    // Download button present
    await expect(page.getByTestId('download-credentials')).toBeVisible();

    // Done button is disabled until checkbox is checked
    await expect(page.getByTestId('done-button')).toBeDisabled();

    // Check the "I have saved" checkbox
    await page.getByTestId('saved-confirmed-checkbox').check();

    // Done button is now enabled
    await expect(page.getByTestId('done-button')).toBeEnabled();
});

test('done button navigates to /calls after confirming credentials saved', async ({ page }) => {
    createSecureLineCredit('e2etoken456');

    await page.goto('/calls/create?token=e2etoken456');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('bridge-number')).toBeVisible({ timeout: 15000 });

    await page.getByTestId('saved-confirmed-checkbox').check();
    await page.getByTestId('done-button').click();

    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL('/calls');
});

test('create page removes pending token from localStorage on success', async ({ page }) => {
    createSecureLineCredit('e2etoken789');

    // Pre-set the pending token
    await page.goto('/calls');
    await page.evaluate(() => {
        localStorage.setItem('secure_line_pending_token', 'e2etoken789');
    });

    await page.goto('/calls/create?token=e2etoken789');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('bridge-number')).toBeVisible({ timeout: 15000 });

    const pendingToken = await page.evaluate(() => localStorage.getItem('secure_line_pending_token'));
    expect(pendingToken).toBeNull();
});

test('participant instructions are shown on credentials page', async ({ page }) => {
    createSecureLineCredit('e2etoken_instruct');

    await page.goto('/calls/create?token=e2etoken_instruct');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('bridge-number')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText(/Join a Line/)).toBeVisible();
});
