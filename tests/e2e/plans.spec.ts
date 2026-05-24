import { test, expect } from '@playwright/test';
import { resetDatabase, seedPlans } from './helpers/db';
import { createTestUser, login } from './helpers/auth';

test.beforeEach(() => {
    resetDatabase();
    seedPlans();
});

test('plans page renders for guest', async ({ page }) => {
    await page.goto('/plans');
    await expect(page.locator('h1, h2').first()).toBeVisible();
    // h5 elements contain plan names — scoped to avoid matching nav/banner text
    await expect(page.locator('h5').first()).toBeVisible();
});

test('plans page renders for authenticated user', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);
    await page.goto('/plans');
    await expect(page.locator('h1, h2').first()).toBeVisible();
    await expect(page.locator('h5').first()).toBeVisible();
});
