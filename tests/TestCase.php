<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable Vite manifest resolution globally so tests don't need the built assets.
        // withoutVite() stubs __invoke and __call but not real methods like asset(),
        // so we swap in a subclass that also overrides asset().
        ViteFacade::clearResolvedInstance();

        $this->swap(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null)
            {
                return new \Illuminate\Support\HtmlString('');
            }

            public function __call($method, $parameters)
            {
                return '';
            }

            public function asset($asset, $buildDirectory = null)
            {
                return '';
            }

            public function __toString()
            {
                return '';
            }
        });
    }
}
