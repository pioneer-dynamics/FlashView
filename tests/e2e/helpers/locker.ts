import { execSync } from 'child_process';
import { Page } from '@playwright/test';

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
 * Create a text locker via the browser UI. Returns passphrase and accountId.
 */
export async function createLockerViaUI(
    page: Page,
    accountId: string,
    passphrase: string,
    content: string,
    creditToken: string
): Promise<{ accountId: string; passphrase: string; updateToken: string }> {
    await page.goto(`/lockers/create?token=${encodeURIComponent(creditToken)}`);
    await page.waitForLoadState('networkidle');

    await page.getByPlaceholder('Choose a 10-digit number').fill(accountId);
    await page.getByPlaceholder('Enter or generate a passphrase').fill(passphrase);
    await page.getByPlaceholder('Enter the content to store…').fill(content);
    await page.getByRole('button', { name: /Encrypt & Create/i }).click();

    // Wait for credentials panel
    await page.waitForSelector('text=Save all three credentials now', { timeout: 15000 });

    // Extract update token from the credentials panel
    const credRows = page.locator('code.font-mono');
    const updateToken = await credRows.nth(2).innerText();

    return { accountId, passphrase, updateToken: updateToken.trim() };
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
