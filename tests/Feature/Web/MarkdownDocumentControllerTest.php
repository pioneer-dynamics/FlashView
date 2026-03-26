<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class MarkdownDocumentControllerTest extends TestCase
{
    public function test_license_page_renders_successfully(): void
    {
        $response = $this->get(route('license.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page')
            ->has('markdown')
            ->where('title', 'MIT License')
        );
    }

    public function test_faq_page_renders_successfully(): void
    {
        $response = $this->get(route('faq.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_terms_page_renders_successfully(): void
    {
        $response = $this->get(route('terms.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_security_page_renders_successfully(): void
    {
        $response = $this->get(route('security.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_privacy_page_renders_successfully(): void
    {
        $response = $this->get(route('policy.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_about_page_renders_successfully(): void
    {
        $response = $this->get(route('about.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_use_cases_page_renders_successfully(): void
    {
        $response = $this->get(route('useCases.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_cli_page_renders_successfully(): void
    {
        $response = $this->get(route('cli.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
    }

    public function test_markdown_pages_replace_config_variables(): void
    {
        $response = $this->get(route('terms.show'));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $markdown = $page->toArray()['props']['markdown'];
            $this->assertStringNotContainsString('{CONFIG:', $markdown);
            $this->assertStringContainsString(config('app.name'), $markdown);
        });
    }

    public function test_markdown_pages_replace_route_variables(): void
    {
        $response = $this->get(route('faq.index'));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $markdown = $page->toArray()['props']['markdown'];
            $this->assertStringNotContainsString('{ROUTE:', $markdown);
        });
    }
}
