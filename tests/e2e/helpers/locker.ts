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
 * Create a locker via the browser UI. Returns passphrase and update_token.
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
