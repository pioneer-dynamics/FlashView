<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BrowserTestCase;
use Tests\TestCase;

// Backend: share TestCase + RefreshDatabase
uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');

// Browser: use BrowserTestCase (skips withoutVite) + RefreshDatabase
// In-process server shares the same DB connection — RefreshDatabase works correctly
// Remove Vite hot file before browser tests (replaces global-setup.ts)
uses(BrowserTestCase::class, RefreshDatabase::class)
    ->beforeAll(function () {
        $hotFile = base_path('public/hot');
        if (file_exists($hotFile)) {
            unlink($hotFile);
        }
    })
    ->in('Browser');
