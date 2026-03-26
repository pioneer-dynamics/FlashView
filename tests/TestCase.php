<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable Vite manifest resolution globally so tests don't need the built assets.
        $this->withoutVite();
    }
}
