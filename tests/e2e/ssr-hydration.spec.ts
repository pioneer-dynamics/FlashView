import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';

test.beforeEach(() => {
    resetDatabase();
});

// ─── Initial HTML: always-valid assertions (pass with or without SSR server) ───

test('dark mode class is present in initial HTML response', async ({ request }) => {
    const response = await request.get('/');
    expect(response.ok()).toBeTruthy();
    const body = await response.text();

    // class="dark" is hardcoded in app.blade.php — prevents FOUC before JS hydrates
    expect(body).toContain('class="dark"');
});

test('inertia head content is injected into initial HTML', async ({ request }) => {
    const response = await request.get('/');
    const body = await response.text();

    // @inertiaHead outputs server-rendered <Head> content (e.g. title tags)
    expect(body).toContain('<title');
});

// ─── SSR: server-rendered markup assertions ────────────────────────────────────
// These tests require the Inertia SSR server to be running (php artisan inertia:start-ssr)
// and a built SSR bundle (npm run build:ssr). They will fail if SSR is not active, which
// correctly indicates the feature is not working.

test('initial HTML response contains server-rendered component markup', async ({ request }) => {
    const response = await request.get('/');
    expect(response.ok()).toBeTruthy();
    const body = await response.text();

    // With SSR: the #app div contains rendered HTML markup
    // Without SSR: #app div has only the data-page attribute and is otherwise empty
    // This assertion verifies SSR is producing rendered markup (not just an empty app shell)
    expect(body).toMatch(/<nav\b/);
    expect(body).toMatch(/<main\b/);
});

test('initial HTML response does not contain an empty app shell', async ({ request }) => {
    const response = await request.get('/');
    const body = await response.text();

    // An empty app shell looks like: <div id="app" data-page="..."></div>
    // SSR renders content inside the div instead
    expect(body).not.toMatch(/<div id="app" data-page="[^"]*"><\/div>/);
});

test('page title is set server-side in initial HTML', async ({ request }) => {
    // The login page uses <Head title="Log In"> — with SSR, this title appears in
    // the raw HTML response before any JavaScript runs
    const response = await request.get('/login');
    const body = await response.text();

    expect(body).toMatch(/<title>Log In\s*[–-]\s*FlashView<\/title>/i);
});

// ─── Hydration: browser-level assertions ──────────────────────────────────────

test('no Vue hydration mismatch warnings on home page', async ({ page }) => {
    const hydrationWarnings: string[] = [];

    page.on('console', (msg) => {
        if (msg.type() === 'warning' && msg.text().includes('[Vue warn]: Hydration')) {
            hydrationWarnings.push(msg.text());
        }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    expect(hydrationWarnings).toHaveLength(0);
});

test('no Vue hydration mismatch warnings on login page', async ({ page }) => {
    const hydrationWarnings: string[] = [];

    page.on('console', (msg) => {
        if (msg.type() === 'warning' && msg.text().includes('[Vue warn]: Hydration')) {
            hydrationWarnings.push(msg.text());
        }
    });

    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    expect(hydrationWarnings).toHaveLength(0);
});

test('home page is interactive after hydration', async ({ page }) => {
    await page.goto('/');

    // Page should be fully rendered and interactive after hydration
    await expect(page.getByRole('navigation')).toBeVisible();
    await expect(page.locator('#message')).toBeVisible();
    await expect(page.getByRole('button', { name: /Generate link/i })).toBeEnabled();
});
