import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';

test.beforeEach(() => {
    resetDatabase();
});

test('pricing page renders with all 6 tier/duration combinations', async ({ page }) => {
    await page.goto('/lockers/buy');
    await page.waitForLoadState('networkidle');

    // Should show two tier sections
    await expect(page.getByText('Text Locker')).toBeVisible();
    await expect(page.getByText('File Locker')).toBeVisible();

    // Should show 6 pricing cards (3 durations × 2 tiers)
    const buyButtons = page.getByRole('button', { name: /Buy \d+-Year Locker/ });
    await expect(buyButtons).toHaveCount(6);
});

test('pricing page shows tier labels and prices', async ({ page }) => {
    await page.goto('/lockers/buy');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('$20')).toBeVisible();
    await expect(page.getByText('$50')).toBeVisible();
    await expect(page.getByText('$80')).toBeVisible();
    await expect(page.getByText('$35')).toBeVisible();
    await expect(page.getByText('$88')).toBeVisible();
    await expect(page.getByText('$140')).toBeVisible();
});

test('pricing page shows human-readable capacity descriptions', async ({ page }) => {
    await page.goto('/lockers/buy');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(/approximately 50 pages/i)).toBeVisible();
    await expect(page.getByText(/documents, images, small archives/i)).toBeVisible();
});

test('pricing page shows anonymity disclaimer', async ({ page }) => {
    await page.goto('/lockers/buy');
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(/no reminders are sent/i)).toBeVisible();
    await expect(page.getByText(/fully anonymous/i)).toBeVisible();
});
