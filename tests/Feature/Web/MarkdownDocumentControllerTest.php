<?php

test('license page renders successfully', function () {
    $response = $this->get(route('license.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page')
        ->has('markdown')
        ->where('title', 'MIT License')
    );
});

test('faq page renders successfully', function () {
    $response = $this->get(route('faq.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('terms page renders successfully', function () {
    $response = $this->get(route('terms.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('security page renders successfully', function () {
    $response = $this->get(route('security.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('privacy page renders successfully', function () {
    $response = $this->get(route('policy.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('about page renders successfully', function () {
    $response = $this->get(route('about.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('use cases page renders successfully', function () {
    $response = $this->get(route('useCases.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('cli page renders successfully', function () {
    $response = $this->get(route('cli.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('webhooks page renders successfully', function () {
    $response = $this->get(route('webhooks.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Doc/Page'));
});

test('markdown pages replace config variables', function () {
    $response = $this->get(route('terms.show'));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $markdown = $page->toArray()['props']['markdown'];
        $this->assertStringNotContainsString('{CONFIG:', $markdown);
        $this->assertStringContainsString(config('app.name'), $markdown);
    });
});

test('markdown pages replace route variables', function () {
    $response = $this->get(route('faq.index'));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $markdown = $page->toArray()['props']['markdown'];
        $this->assertStringNotContainsString('{ROUTE:', $markdown);
    });
});
