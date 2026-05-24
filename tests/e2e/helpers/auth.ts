import { Page } from '@playwright/test';
import { execSync } from 'child_process';

const ARTISAN = process.env.CI ? 'php artisan' : 'vendor/bin/sail artisan';

export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
}

export async function logout(page: Page): Promise<void> {
    // Fortify requires POST for /logout — page.goto('/logout') sends GET and returns 405.
    // Open the user-menu dropdown first, then click Log Out, mirroring how Inertia fires router.post(route('logout')).
    await page.getByTestId('user-menu-trigger').click();
    await page.getByRole('button', { name: 'Log Out' }).click();
    await page.waitForURL('/');
}

export function createTestUser(): { email: string; password: string } {
    const email = `e2e-${Date.now()}@example.com`;
    const password = 'password';
    execSync(
        `${ARTISAN} tinker --no-interaction --env=testing --execute="App\\\\Models\\\\User::factory()->create(['email' => '${email}', 'password' => bcrypt('${password}')])"`,
        { stdio: 'pipe' }
    );
    return { email, password };
}
