<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class BrowserTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Do NOT call withoutVite() — browser tests need real browser assets
    }
}
