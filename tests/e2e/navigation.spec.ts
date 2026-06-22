import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createTestUser, login, createAdminUser } from './helpers/auth';

test.beforeEach(() => {
    resetDatabase();
});

// ─── Guest Navigation ──────────────────────────────────────────────────────────

test('guest sees correct top-level nav links', async ({ page }) => {
    await page.goto('/');

    // Primary nav items visible to guests
    await expect(page.getByRole('navigation').getByRole('link', { name: 'New Secret' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Pricing' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Secure Line' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Blog' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'F.A.Q.' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'About Us' })).toBeVisible();

    // Auth links visible to guests
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Log in' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Register' })).toBeVisible();
});

test('guest New Secret nav link points to /', async ({ page }) => {
    await page.goto('/plans');
    await page.getByRole('navigation').getByRole('link', { name: 'New Secret' }).first().click();
    await expect(page).toHaveURL('/');
});

test('guest Pricing nav link navigates to /plans', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'Pricing' }).first().click();
    await expect(page).toHaveURL('/plans');
});

test('guest eLocker dropdown shows Access and Buy links', async ({ page }) => {
    await page.goto('/');

    // Open the eLocker dropdown
    await page.getByRole('navigation').getByRole('button', { name: /eLocker/i }).first().click();
    await expect(page.getByRole('link', { name: 'Access My Locker' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Buy a Locker' })).toBeVisible();
});

test('guest eLocker Access link navigates to /lockers', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('button', { name: /eLocker/i }).first().click();
    await page.getByRole('link', { name: 'Access My Locker' }).click();
    await expect(page).toHaveURL('/lockers');
});

test('guest eLocker Buy link navigates to /lockers/buy', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('button', { name: /eLocker/i }).first().click();
    await page.getByRole('link', { name: 'Buy a Locker' }).click();
    await expect(page).toHaveURL('/lockers/buy');
});

test('guest Secure Line nav link navigates to /calls', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'Secure Line' }).first().click();
    await expect(page).toHaveURL('/calls');
});

test('guest Blog nav link navigates to /blog', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'Blog' }).first().click();
    await expect(page).toHaveURL('/blog');
});

test('guest FAQ nav link navigates to /faq', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'F.A.Q.' }).first().click();
    await expect(page).toHaveURL('/faq');
});

test('guest About Us nav link navigates to /about', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'About Us' }).first().click();
    await expect(page).toHaveURL('/about');
});

test('guest Log in link navigates to /login', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'Log in' }).click();
    await expect(page).toHaveURL('/login');
});

test('guest Register link navigates to /register', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation').getByRole('link', { name: 'Register' }).click();
    await expect(page).toHaveURL('/register');
});

// ─── Active-state highlighting ─────────────────────────────────────────────────

test('Pricing nav link has active state when on /plans', async ({ page }) => {
    await page.goto('/plans');
    const pricingLink = page.getByRole('navigation').getByRole('link', { name: 'Pricing' }).first();
    await expect(pricingLink).toHaveClass(/border-gamboge-700/);
});

test('Secure Line nav link has active state when on /calls', async ({ page }) => {
    await page.goto('/calls');
    const secureLineLink = page.getByRole('navigation').getByRole('link', { name: 'Secure Line' }).first();
    await expect(secureLineLink).toHaveClass(/border-gamboge-700/);
});

test('Blog nav link has active state when on /blog', async ({ page }) => {
    await page.goto('/blog');
    const blogLink = page.getByRole('navigation').getByRole('link', { name: 'Blog' }).first();
    await expect(blogLink).toHaveClass(/border-gamboge-700/);
});

// ─── Authenticated User Navigation ────────────────────────────────────────────

test('authenticated user sees New Secret and My Secrets nav links', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await expect(page.getByRole('navigation').getByRole('link', { name: 'New Secret' })).toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'My Secrets' })).toBeVisible();
});

test('authenticated user does not see Login or Register links', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await expect(page.getByRole('navigation').getByRole('link', { name: 'Log in' })).not.toBeVisible();
    await expect(page.getByRole('navigation').getByRole('link', { name: 'Register' })).not.toBeVisible();
});

test('authenticated user New Secret nav link navigates to /dashboard', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.goto('/plans');
    await page.getByRole('navigation').getByRole('link', { name: 'New Secret' }).first().click();
    await expect(page).toHaveURL('/dashboard');
});

test('authenticated user My Secrets nav link navigates to /secrets', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.getByRole('navigation').getByRole('link', { name: 'My Secrets' }).first().click();
    await expect(page).toHaveURL('/secrets');
});

test('authenticated user New Secret has active state on /dashboard', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    const newSecretLink = page.getByRole('navigation').getByRole('link', { name: 'New Secret' }).first();
    await expect(newSecretLink).toHaveClass(/border-gamboge-700/);
});

test('authenticated user user-menu dropdown shows Profile and Settings links', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.getByTestId('user-menu-trigger').click();
    await expect(page.getByRole('link', { name: 'Profile' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Notification Settings' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Misc Settings' })).toBeVisible();
});

test('authenticated user Profile dropdown link navigates to /user/profile', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.getByTestId('user-menu-trigger').click();
    await page.getByRole('link', { name: 'Profile' }).click();
    await expect(page).toHaveURL('/user/profile');
});

test('authenticated user Notification Settings link navigates to /user/notification-settings', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.getByTestId('user-menu-trigger').click();
    await page.getByRole('link', { name: 'Notification Settings' }).click();
    await expect(page).toHaveURL('/user/notification-settings');
});

test('authenticated user Misc Settings link navigates to /user/settings', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await page.getByTestId('user-menu-trigger').click();
    await page.getByRole('link', { name: 'Misc Settings' }).click();
    await expect(page).toHaveURL('/user/settings');
});

// ─── Admin Navigation ──────────────────────────────────────────────────────────

test('admin user sees Admin dropdown in nav', async ({ page }) => {
    const { email, password } = createAdminUser();
    await login(page, email, password);

    await expect(page.getByRole('navigation').getByRole('button', { name: /Admin/i })).toBeVisible();
});

test('admin user Admin dropdown shows all admin links', async ({ page }) => {
    const { email, password } = createAdminUser();
    await login(page, email, password);

    await page.getByRole('navigation').getByRole('button', { name: /Admin/i }).click();
    await expect(page.getByRole('link', { name: 'Subscription Plans' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'eLocker Plans' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Secure Line Products' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Coupons' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Manage Users' })).toBeVisible();
});

test('admin Subscription Plans link navigates to /admin/plans', async ({ page }) => {
    const { email, password } = createAdminUser();
    await login(page, email, password);

    await page.getByRole('navigation').getByRole('button', { name: /Admin/i }).click();
    await page.getByRole('link', { name: 'Subscription Plans' }).click();
    await expect(page).toHaveURL('/admin/plans');
});

test('admin Manage Users link navigates to /admin/users', async ({ page }) => {
    const { email, password } = createAdminUser();
    await login(page, email, password);

    await page.getByRole('navigation').getByRole('button', { name: /Admin/i }).click();
    await page.getByRole('link', { name: 'Manage Users' }).click();
    await expect(page).toHaveURL('/admin/users');
});

test('non-admin user does not see Admin dropdown', async ({ page }) => {
    const { email, password } = createTestUser();
    await login(page, email, password);

    await expect(page.getByRole('navigation').getByRole('button', { name: /Admin/i })).not.toBeVisible();
});

// ─── Footer navigation ─────────────────────────────────────────────────────────

test('footer Blog link navigates to /blog', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('contentinfo').getByRole('link', { name: 'Blog' }).click();
    await expect(page).toHaveURL('/blog');
});

test('footer Pricing link navigates to /plans', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('contentinfo').getByRole('link', { name: 'Pricing' }).click();
    await expect(page).toHaveURL('/plans');
});

test('footer Privacy Policy link navigates to /privacy-policy', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('contentinfo').getByRole('link', { name: 'Privacy Policy' }).click();
    await expect(page).toHaveURL('/privacy-policy');
});
