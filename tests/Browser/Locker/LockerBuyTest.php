<?php

test('pricing page renders with all 6 tier/duration combinations', function () {
    $page = visit('/lockers/buy');

    $page->assertSee('Text Locker');
    $page->assertSee('File Locker');

    // Should show 6 pricing cards (3 durations × 2 tiers) — each has a buy button
    $page->assertSee('Buy 1-Year Locker');
    $page->assertSee('Buy 3-Year Locker');
    $page->assertSee('Buy 5-Year Locker');
});

test('pricing page shows tier labels and prices', function () {
    $page = visit('/lockers/buy');

    $page->assertSee('$20');
    $page->assertSee('$50');
    $page->assertSee('$80');
    $page->assertSee('$35');
    $page->assertSee('$88');
    $page->assertSee('$140');
});

test('pricing page shows human-readable capacity descriptions', function () {
    $page = visit('/lockers/buy');

    $page->assertSee('approximately 50 pages');
    $page->assertSee('documents, images, small archives');
});

test('pricing page shows anonymity disclaimer', function () {
    $page = visit('/lockers/buy');

    $page->assertSee('reminders');
    $page->assertSee('anonymous');
});
