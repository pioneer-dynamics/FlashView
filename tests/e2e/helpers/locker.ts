import { execSync } from 'child_process';
import { Page } from '@playwright/test';

/**
 * Navigate to the locker renew page via the sessionStorage prefill pattern.
 * The account ID is stored in sessionStorage before navigating to /lockers/renew
 * so it never appears in the URL.
 */
export async function navigateToLockerRenew(page: Page, accountId: string): Promise<void> {
    await page.goto('/lockers/open');
    await page.waitForLoadState('networkidle');
    await page.evaluate((id) => {
        sessionStorage.setItem('locker_prefill_account_renew', id);
    }, accountId);
    await page.goto('/lockers/renew');
    await page.waitForLoadState('networkidle');
}

/**
 * Navigate to the locker open page, enter the account number, and wait for the unlock form.
 * Works for all auth modes (passphrase, key_file, combined) since it waits on unlock-button,
 * not passphrase-input (which is absent in key_file mode).
 */
export async function navigateToLocker(page: Page, accountId: string): Promise<void> {
    await page.goto('/lockers/open');
    await page.waitForLoadState('networkidle');
    await page.getByTestId('account-id-input').fill(accountId);
    await page.getByTestId('open-button').click();
    // Wait for unlock-button — present in ALL auth modes (passphrase, key_file, combined)
    await page.getByTestId('unlock-button').waitFor({ state: 'visible', timeout: 5000 });
}

const ARTISAN = process.env.CI ? 'php artisan' : 'vendor/bin/sail artisan';

/**
 * Insert a LockerCredit row via Artisan tinker.
 */
export function createLockerCredit(
    token: string,
    tier: string = 'text',
    years: number = 1
): void {
    execSync(
        `${ARTISAN} tinker --no-interaction --env=testing --execute="App\\\\Models\\\\LockerCredit::create(['token' => '${token}', 'tier' => '${tier}', 'years' => ${years}])"`,
        { stdio: 'pipe' }
    );
}

/**
 * Create a legacy (HMAC) locker directly in the database via tinker, bypassing the UI.
 * Used for testing the upgrade flow — the UI now always creates ECDSA lockers.
 */
export function createLegacyLockerViaDB(
    accountId: string,
    passphrase: string,
    content: string
): void {
    const script = [
        `$l = App\\\\Models\\\\Locker::create([`,
        `'account_id' => '${accountId}',`,
        `'payload' => '01546'.'${content}'.str_repeat('0',40),`,
        `'auth_challenge' => str_repeat('c',64),`,
        `'auth_verifier' => str_repeat('a',64),`,
        `'update_token_hash' => hash('sha256','legacytoken'),`,
        `'expires_at' => now()->addYear(),`,
        `]);`,
    ].join(' ');
    execSync(
        `${ARTISAN} tinker --no-interaction --env=testing --execute="${script}"`,
        { stdio: 'pipe' }
    );
}

/**
 * Create a text locker via the browser UI (ECDSA path). Returns passphrase and accountId.
 * New lockers use ECDSA signing — no update token is stored or shown.
 */
export async function createLockerViaUI(
    page: Page,
    accountId: string,
    passphrase: string,
    content: string,
    creditToken: string
): Promise<{ accountId: string; passphrase: string }> {
    await page.goto(`/lockers/create?token=${encodeURIComponent(creditToken)}`);
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill(accountId);
    await page.getByPlaceholder('Enter or generate a passphrase').fill(passphrase);
    await page.getByPlaceholder('Enter the content to store…').fill(content);
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    // Wait for credentials panel (ECDSA: only Account ID + Passphrase shown)
    await page.waitForSelector('text=Locker created!', { timeout: 15000 });

    return { accountId, passphrase };
}

/** Deterministic test key file fixtures. Use these for all key-file E2E tests. */
export const KEY_FILE_ALPHA = {
    name: 'key-file-alpha.bin',
    mimeType: 'application/octet-stream',
    buffer: Buffer.from('e2e-key-file-alpha-content-unique-v1'),
};

export const KEY_FILE_BETA = {
    name: 'key-file-beta.bin',
    mimeType: 'application/octet-stream',
    buffer: Buffer.from('e2e-key-file-beta-content-unique-v1'),
};

export const KEY_FILE_WRONG = {
    name: 'key-file-wrong.bin',
    mimeType: 'application/octet-stream',
    buffer: Buffer.from('e2e-key-file-wrong-content-should-fail'),
};

/**
 * Create a key-file-only locker via the browser UI.
 * Accepts one or more key file fixtures (from KEY_FILE_ALPHA, etc.).
 */
export async function createKeyFileLockerViaUI(
    page: Page,
    accountId: string,
    content: string,
    creditToken: string,
    keyFiles: { name: string; mimeType: string; buffer: Buffer }[]
): Promise<{ accountId: string; keyFiles: typeof keyFiles }> {
    await page.goto(`/lockers/create?token=${encodeURIComponent(creditToken)}`);
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill(accountId);
    await page.getByRole('button', { name: 'Key File(s)' }).click();

    for (const kf of keyFiles) {
        await page.getByTestId('key-file-input').setInputFiles(kf);
    }

    await page.getByTestId('key-file-risk-checkbox').check();
    await page.getByPlaceholder('Enter the content to store…').fill(content);
    await page.getByTestId('create-submit-button').click();

    await page.waitForSelector('text=Locker created!', { timeout: 15000 });

    return { accountId, keyFiles };
}

/**
 * Create a combined (passphrase + key file) locker via the browser UI.
 */
export async function createCombinedLockerViaUI(
    page: Page,
    accountId: string,
    passphrase: string,
    content: string,
    creditToken: string,
    keyFiles: { name: string; mimeType: string; buffer: Buffer }[]
): Promise<{ accountId: string; passphrase: string; keyFiles: typeof keyFiles }> {
    await page.goto(`/lockers/create?token=${encodeURIComponent(creditToken)}`);
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill(accountId);
    await page.getByRole('button', { name: 'Both' }).click();
    await page.getByPlaceholder('Enter or generate a passphrase').fill(passphrase);

    for (const kf of keyFiles) {
        await page.getByTestId('key-file-input').setInputFiles(kf);
        await page.waitForTimeout(300);
    }

    await page.getByTestId('key-file-risk-checkbox').check();
    await page.getByPlaceholder('Enter the content to store…').fill(content);
    await page.getByTestId('create-submit-button').click();

    await page.waitForSelector('text=Locker created!', { timeout: 15000 });

    return { accountId, passphrase, keyFiles };
}

/**
 * Create a file locker via the browser UI with mocked S3 endpoints.
 * S3 is not available in the test environment — routes are intercepted.
 */
export async function createFileLockerViaUI(
    page: Page,
    accountId: string,
    passphrase: string,
    creditToken: string,
    storagePath: string = 'lockers/test-file.bin'
): Promise<{ accountId: string; passphrase: string; storagePath: string }> {
    const s3MockUrl = `https://mock-s3-locker.example.com/${storagePath}`;

    await page.route('**/lockers/file/prepare', async route => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                upload_type: 's3_direct',
                upload_url: s3MockUrl,
                upload_headers: { 'Content-Type': 'application/octet-stream' },
                storage_path: storagePath,
            }),
        });
    });
    await page.route('https://mock-s3-locker.example.com/**', async route => {
        await route.fulfill({ status: 200, body: '' });
    });

    await page.goto(`/lockers/create?token=${encodeURIComponent(creditToken)}`);
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill(accountId);
    await page.getByPlaceholder('Enter or generate a passphrase').fill(passphrase);

    await page.getByLabel(/file/i).setInputFiles({
        name: 'test-file.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('file locker test content for ' + accountId),
    });

    await page.getByRole('button', { name: /Encrypt & Create/i }).click();
    await page.waitForSelector('text=Locker created!', { timeout: 20000 });

    return { accountId, passphrase, storagePath };
}
