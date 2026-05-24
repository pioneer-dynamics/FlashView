import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? [['github'], ['html']] : 'html',
    use: {
        // Locally: Sail nginx on port 80. In CI: php artisan serve on port 8000 (APP_URL is set in the workflow).
        baseURL: process.env.APP_URL ?? 'http://localhost',
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    // In CI the server is started explicitly in the workflow before tests run; we just reuse it.
    // Locally, Sail's nginx serves the app on port 80.
    ...(process.env.CI
        ? {
              webServer: {
                  command: 'php artisan serve --port=8000',
                  url: 'http://localhost:8000',
                  reuseExistingServer: true,
              },
          }
        : {}),
});
