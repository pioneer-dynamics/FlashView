<?php

// File upload requires an authenticated user with a plan that permits uploads.
// seedPlans() creates the Free plan which allows up to 10 MB.

test('authenticated user creates a file secret and recipient downloads it', function () {
    todo("Requires page.waitForEvent('download') — not available in pest-plugin-browser v4");
});

test('file secret is inaccessible after download', function () {
    todo("Requires page.waitForEvent('download') — not available in pest-plugin-browser v4");
});
