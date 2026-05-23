import { Page } from '@playwright/test';

export async function createSecret(
    page: Page,
    message: string,
    options: { password?: string } = {}
): Promise<{ shareUrl: string; passphrase: string }> {
    await page.goto('/');
    await page.fill('#message', message);
    if (options.password) {
        await page.fill('#password', options.password);
    }
    await page.click('button:has-text("Generate link")');
    await page.waitForSelector('text=Please share the link and password separately');

    const shareUrl = await page.getByTestId('share-url').locator('code').innerText();
    const passphrase = await page.getByTestId('passphrase').locator('code').innerText();

    return { shareUrl: shareUrl.trim(), passphrase: passphrase.trim() };
}
