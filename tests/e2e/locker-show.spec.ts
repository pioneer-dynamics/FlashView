import { test, expect } from '@playwright/test';
import { resetDatabase, clearCache } from './helpers/db';
import { createLockerCredit, createLockerViaUI, createFileLockerViaUI, createLegacyLockerViaDB, navigateToLocker } from './helpers/locker';

test.beforeEach(() => {
    resetDatabase();
});

test('visiting old locker URL redirects to open page', async ({ page }) => {
    await page.goto('/lockers/9999999999');
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL('/lockers/open');
    await expect(page.getByTestId('account-id-input')).toBeVisible();
});

test('navigating to /lockers/open shows account entry form', async ({ page }) => {
    await page.goto('/lockers/open');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('account-id-input')).toBeVisible();
    await expect(page.getByTestId('open-button')).toBeVisible();
});

test('lock icon is visible before unlock is attempted', async ({ page }) => {
    createLockerCredit('showtoken01', 'text', 1);
    await createLockerViaUI(page, '2222222222', 'my-show-passphrase-long', 'Test content', 'showtoken01');

    await navigateToLocker(page, '2222222222');

    await expect(page.getByTestId('lock-icon')).toBeVisible();
    await expect(page.getByTestId('passphrase-input')).toBeVisible();
    await expect(page.getByTestId('unlock-button')).toBeVisible();
});

test('correct passphrase decrypts and displays content', async ({ page }) => {
    createLockerCredit('showtoken02', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '3333333333', 'my-correct-passphrase-long', 'My secret locker text', 'showtoken02');

    await navigateToLocker(page, '3333333333');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('My secret locker text')).toBeVisible();
});

test('URL stays at /lockers/open throughout the unlock flow', async ({ page }) => {
    createLockerCredit('showtoken02b', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '3344556677', 'url-check-passphrase-long', 'URL check content', 'showtoken02b');

    await navigateToLocker(page, '3344556677');
    await expect(page).toHaveURL('/lockers/open');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await expect(page).toHaveURL('/lockers/open');
});

test('after submitting correct passphrase, lock animation plays and content is revealed', async ({ page }) => {
    createLockerCredit('showtoken03', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '4444444444', 'my-anim-passphrase-long', 'Animation test content', 'showtoken03');

    await navigateToLocker(page, '4444444444');

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

    await navigateToLocker(page, '5555555555');

    await page.getByTestId('passphrase-input').fill('wrong-passphrase-here-!');
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypt-error')).toBeVisible({ timeout: 10000 });
    await expect(page.getByText(/Credentials do not match|Decryption failed/i)).toBeVisible();
});

test('after submitting wrong passphrase, lock shake animation plays and error message appears', async ({ page }) => {
    createLockerCredit('showtoken05', 'text', 1);
    await createLockerViaUI(page, '6666666666', 'my-real-passphrase-long', 'Content for shake test', 'showtoken05');

    await navigateToLocker(page, '6666666666');

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

    await navigateToLocker(page, '7777777777');

    // Two wrong passphrase attempts — clear cache between to avoid rate-limiter blocking 2nd fetch
    for (let i = 0; i < 2; i++) {
        await page.getByTestId('unlock-button').waitFor({ state: 'visible' });
        await page.getByTestId('passphrase-input').fill('wrong-pass-attempt');
        await page.getByTestId('unlock-button').click();
        await page.getByTestId('decrypt-error').waitFor({ state: 'visible', timeout: 10000 });
        clearCache();
    }

    await expect(page.getByText(/credentials are lost/i)).toBeVisible();
});

test('update hint visible in locked state; update panel visible after unlock', async ({ page }) => {
    createLockerCredit('showtoken07', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '8888888888', 'my-real-passphrase-long', 'Content for update panel test', 'showtoken07');

    await navigateToLocker(page, '8888888888');

    // Locked state: shows hint, not the update panel
    await expect(page.getByText('Unlock your locker to update or delete it.')).toBeVisible();

    // Unlock to see the update panel
    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await expect(page.getByText('Update Content')).toBeVisible();
    await expect(page.getByTestId('update-button')).toBeVisible();
});

test('expiry badge is visible after unlock', async ({ page }) => {
    createLockerCredit('showtoken08', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '9999111111', 'my-real-passphrase-long', 'Content for expiry badge test', 'showtoken08');

    await navigateToLocker(page, '9999111111');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await expect(page.getByText(/days remaining|Expires/i)).toBeVisible();
    await expect(page.getByText('Renew')).toBeVisible();
});

// ─── DEK-based file locker flows (PIO-102) ───────────────────────────────────

test('file locker shows file metadata after unlock', async ({ page }) => {
    createLockerCredit('fileshow01', 'file', 1);
    const passphrase = 'my-file-locker-passphrase';
    await createFileLockerViaUI(page, '1122334455', passphrase, 'fileshow01');

    await navigateToLocker(page, '1122334455');

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

    await navigateToLocker(page, '2233445566');

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

// ─── ECDSA flows (PIO-103) ────────────────────────────────────────────────────

test('ECDSA locker unlock decrypts and displays content', async ({ page }) => {
    createLockerCredit('ecdsashow01', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '5100000001', 'ecdsa-unlock-passphrase', 'ECDSA secret text', 'ecdsashow01');

    await navigateToLocker(page, '5100000001');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('ECDSA secret text')).toBeVisible();
});

test('ECDSA locker update content shows success message', async ({ page }) => {
    createLockerCredit('ecdsashow02', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '5100000002', 'ecdsa-update-passphrase', 'Original content', 'ecdsashow02');

    await navigateToLocker(page, '5100000002');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await page.getByPlaceholder('New content…').fill('Updated secret content');
    await page.getByTestId('update-button').click();

    await expect(page.getByText('Content updated.')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Updated secret content')).toBeVisible();
});

test('ECDSA locker delete redirects to home', async ({ page }) => {
    createLockerCredit('ecdsashow03', 'text', 1);
    const { passphrase } = await createLockerViaUI(page, '5100000003', 'ecdsa-delete-passphrase', 'Content to delete', 'ecdsashow03');

    await navigateToLocker(page, '5100000003');

    await page.getByTestId('passphrase-input').fill(passphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await page.getByRole('button', { name: /Delete this locker permanently/i }).click();
    await page.getByTestId('confirm-delete-button').click();

    await expect(page).toHaveURL('/', { timeout: 15000 });
});

test('ECDSA locker passphrase change — new passphrase unlocks, old one fails', async ({ page }) => {
    createLockerCredit('ecdsashow04', 'text', 1);
    const oldPassphrase = 'ecdsa-old-passphrase-secure';
    const newPassphrase = 'ecdsa-new-passphrase-secure';
    const { accountId } = await createLockerViaUI(page, '5100000004', oldPassphrase, 'Passphrase change content', 'ecdsashow04');

    // Unlock with old passphrase and change it
    await navigateToLocker(page, accountId);

    await page.getByTestId('passphrase-input').fill(oldPassphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });

    await page.getByPlaceholder('Enter or generate a passphrase').last().fill(newPassphrase);
    await page.getByRole('button', { name: /Change Passphrase/i }).click();
    await expect(page.getByText(/Passphrase changed/i)).toBeVisible({ timeout: 15000 });

    // Lock — returns to account entry phase
    await page.getByTestId('lock-button').click();

    // Re-enter account number on the entry form
    await page.getByTestId('account-id-input').fill(accountId);
    await page.getByTestId('open-button').click();
    await page.getByTestId('unlock-button').waitFor({ state: 'visible', timeout: 5000 });

    // Try old passphrase — should fail
    await page.getByTestId('passphrase-input').fill(oldPassphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypt-error')).toBeVisible({ timeout: 10000 });

    // Clear rate limiters (the failed attempt hit the cooldown) and wait for shake to end
    clearCache();
    await expect(page.getByTestId('unlock-button')).toBeEnabled({ timeout: 3000 });

    // Try new passphrase — should succeed
    await page.getByTestId('passphrase-input').fill(newPassphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
});

test('upgrade banner appears after legacy unlock and upgrade migrates to ECDSA', async ({ page }) => {
    createLockerCredit('ecdsashow05', 'text', 1);
    const upgradePassphrase = 'upgrade-banner-passphrase';
    const upgradeAccountId = '5100000005';

    // Capture the encrypted payload from the creation request body before it hits the server
    let capturedPayload: string | null = null;
    await page.route('**/lockers', async (route, request) => {
        if (request.method() === 'POST' && !request.url().includes('file')) {
            const body = JSON.parse(request.postData() || '{}');
            if (body.account_id === upgradeAccountId) {
                capturedPayload = body.payload;
            }
        }
        await route.continue();
    });

    await createLockerViaUI(page, upgradeAccountId, upgradePassphrase, 'Banner test content', 'ecdsashow05');

    // Use context-level routes (persist across navigations, evaluated before page routes)
    const challengePattern = new RegExp(`/lockers/${upgradeAccountId}/challenge`);
    const unlockPattern = new RegExp(`/lockers/${upgradeAccountId}/unlock`);
    const upgradePattern = new RegExp(`/lockers/${upgradeAccountId}/upgrade-auth`);

    const futureExpiry = new Date(Date.now() + 365 * 86400 * 1000).toISOString();

    // Mock challenge as legacy (no challenge_id) to trigger the upgrade banner
    await page.context().route(challengePattern, async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ challenge: 'a'.repeat(64) }),
        });
    });

    // Mock unlock to return the real encrypted payload (so decryption succeeds)
    await page.context().route(unlockPattern, async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                payload: capturedPayload,
                is_file_locker: false,
                expires_at: futureExpiry,
                auth_challenge: 'a'.repeat(64),
            }),
        });
    });

    await page.context().route(upgradePattern, async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ ok: true }),
        });
    });

    // Navigate to open page with mocks already active
    await navigateToLocker(page, upgradeAccountId);

    await page.getByTestId('passphrase-input').fill(upgradePassphrase);
    await page.getByTestId('unlock-button').click();

    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('upgrade-banner')).toBeVisible({ timeout: 5000 });
    await expect(page.getByText('Security upgrade available')).toBeVisible();

    await page.getByTestId('upgrade-button').click();

    await expect(page.getByTestId('upgrade-success')).toBeVisible({ timeout: 10000 });
    await expect(page.getByTestId('upgrade-banner')).not.toBeVisible();
});

test('upgrade banner can be dismissed without upgrading', async ({ page }) => {
    createLockerCredit('ecdsashow06', 'text', 1);
    const dismissPassphrase = 'dismiss-banner-passphrase';
    const dismissAccountId = '5100000006';

    let capturedPayload2: string | null = null;
    await page.route('**/lockers', async (route, request) => {
        if (request.method() === 'POST' && !request.url().includes('file')) {
            const body = JSON.parse(request.postData() || '{}');
            if (body.account_id === dismissAccountId) {
                capturedPayload2 = body.payload;
            }
        }
        await route.continue();
    });

    await createLockerViaUI(page, dismissAccountId, dismissPassphrase, 'Dismiss test content', 'ecdsashow06');

    const challengePattern2 = new RegExp(`/lockers/${dismissAccountId}/challenge`);
    const unlockPattern2 = new RegExp(`/lockers/${dismissAccountId}/unlock`);
    const futureExpiry2 = new Date(Date.now() + 365 * 86400 * 1000).toISOString();

    await page.context().route(challengePattern2, async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ challenge: 'b'.repeat(64) }),
        });
    });

    await page.context().route(unlockPattern2, async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                payload: capturedPayload2,
                is_file_locker: false,
                expires_at: futureExpiry2,
                auth_challenge: 'b'.repeat(64),
            }),
        });
    });

    await navigateToLocker(page, dismissAccountId);

    await page.getByTestId('passphrase-input').fill(dismissPassphrase);
    await page.getByTestId('unlock-button').click();
    await expect(page.getByTestId('decrypted-content')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('upgrade-banner')).toBeVisible({ timeout: 5000 });

    await page.getByTestId('upgrade-dismiss-button').click();
    await expect(page.getByTestId('upgrade-banner')).not.toBeVisible();
});
