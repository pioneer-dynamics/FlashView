import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createTestUser, login } from './helpers/auth';

test.beforeEach(async () => {
    resetDatabase();
});

test('plans page renders for guest', async ({ page }) => {
    await page.goto('/plans');
    await expect(page.locator('h1, h2').first()).toBeVisible();
    // Verify at least one plan name is visible so a blank render is caught
    await expect(page.locator('text=Free').or(page.locator('text=Pro')).or(page.locator('text=Prime'))).toBeVisible();
});

test('plans page renders for authenticated user', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);
    await page.goto('/plans');
    await expect(page.locator('h1, h2').first()).toBeVisible();
    await expect(page.locator('text=Free').or(page.locator('text=Pro')).or(page.locator('text=Prime'))).toBeVisible();
});
