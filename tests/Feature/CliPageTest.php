<?php

use Inertia\Testing\AssertableInertia;

test('cli page returns 200', function () {
    $response = $this->get('/cli');

    $response->assertStatus(200);
});

test('cli page renders correct inertia component', function () {
    $response = $this->get('/cli');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Doc/Page')
        ->where('title', 'CLI Tool')
        ->where('showUpdatedAt', false)
        ->has('markdown')
    );
});
