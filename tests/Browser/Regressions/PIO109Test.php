<?php

/**
 * @ticket PIO-109
 *
 * @symptom File download fails after 15 minutes because the presigned S3 URL was
 *          generated eagerly at unlock time and cached in component state, expiring
 *          before the user clicks "Decrypt & Download".
 *
 * Must fail before the fix (download_url cached at unlock), pass after (on-demand fetch).
 */
test('download button is visible after unlocking a file locker', function () {
    todo('Requires page.route() network interception for S3 file locker mocking — not available in pest-plugin-browser v4');
});

test('clicking download fetches a fresh URL from /download-url endpoint (not a cached URL)', function () {
    todo('Requires page.route() network interception for S3 file locker mocking — not available in pest-plugin-browser v4');
});

test('download shows session-expired error message when download-url returns 403', function () {
    todo('Requires page.route() network interception for S3 file locker mocking — not available in pest-plugin-browser v4');
});
