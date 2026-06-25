<?php

// ─── Initial HTML: always-valid assertions (pass with or without SSR server) ───

test('dark mode class is present in initial HTML response', function () {
    // class="dark" is hardcoded in app.blade.php — prevents FOUC before JS hydrates
    $page = visit('/');
    $page->assertSourceHas('class="dark"');
});

test('inertia head content is injected into initial HTML', function () {
    // @inertiaHead outputs server-rendered <Head> content (e.g. title tags)
    $this->get('/')
        ->assertSee('<title', false);
});

// ─── SSR: server-rendered markup assertions ────────────────────────────────────

test('initial HTML response contains server-rendered component markup', function () {
    todo('Requires Inertia SSR server (inertia:start-ssr) — not started in CI');
});

test('initial HTML response does not contain an empty app shell', function () {
    todo('Requires Inertia SSR server (inertia:start-ssr) — not started in CI');
});

test('page title is set server-side in initial HTML', function () {
    todo('Requires Inertia SSR server (inertia:start-ssr) — not started in CI');
});

// ─── Hydration: browser-level assertions ──────────────────────────────────────

test('no Vue hydration mismatch warnings on home page', function () {
    todo('Requires console.warn interception — pest-plugin-browser only captures console.log and window.onerror, not console.warn');
});

test('no Vue hydration mismatch warnings on login page', function () {
    todo('Requires console.warn interception — pest-plugin-browser only captures console.log and window.onerror, not console.warn');
});

test('home page is interactive after hydration', function () {
    $page = visit('/');

    $page->assertPresent('nav');
    $page->assertPresent('#message');
    $page->assertButtonEnabled('Generate link');
});
