<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CliPageTest extends TestCase
{
    public function test_cli_page_returns_200(): void
    {
        $response = $this->get('/cli');

        $response->assertStatus(200);
    }

    public function test_cli_page_renders_correct_inertia_component(): void
    {
        $response = $this->get('/cli');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Doc/Page')
            ->where('title', 'CLI Tool')
            ->where('showUpdatedAt', false)
            ->has('markdown')
        );
    }
}
