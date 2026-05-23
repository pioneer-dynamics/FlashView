import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createTestUser, login, logout } from './helpers/auth';

test.beforeEach(async () => {
    resetDatabase();
});

test('user can log in with valid credentials', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);
    await expect(page).toHaveURL(/dashboard/);
});

test('login fails with invalid password', async ({ page }) => {
    const { email } = createTestUser();
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', 'wrong-password');
    await page.click('button[type="submit"]');

    await expect(page.locator('text=These credentials do not match our records')).toBeVisible();
});

test('authenticated user can log out', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);
    await logout(page);
    await expect(page).toHaveURL('/');
});
