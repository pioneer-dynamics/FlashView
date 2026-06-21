import { test, expect } from '@playwright/test';
import { resetDatabase } from './helpers/db';
import { createActiveCallSession } from './helpers/calls';

/**
 * Full WebRTC room interaction tests (media tracks, peer connection establishment)
 * require two concurrent browser contexts with media stream mocking. These are
 * deferred to a dedicated follow-up E2E ticket.
 *
 * This file covers only the guards and expiry-adjacent behaviours that can be
 * tested without live media or a second participant.
 */

test.beforeEach(() => {
    resetDatabase();
});

test('navigating directly to /room without sessionStorage redirects to the join page', async ({ page }) => {
    const hashId = createActiveCallSession();

    await page.goto(`/calls/${hashId}/room`);
    await page.waitForLoadState('networkidle');

    // Room.vue reads sessionStorage on mount; if missing it immediately redirects
    await expect(page).toHaveURL(`/calls/${hashId}`);
    await expect(page.getByTestId('call-password-input')).toBeVisible();
});

test('navigating to an invalid bridge number on /room returns a 404', async ({ page }) => {
    const response = await page.goto('/calls/invalidhash000/room');
    expect(response?.status()).toBe(404);
});
