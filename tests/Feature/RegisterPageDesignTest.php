<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('register page renders correct component', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/Register')
    );
});

test('register complete page renders correct component', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $signedUrl = URL::temporarySignedRoute(
        'register.complete',
        now()->addMinutes(120),
        ['email' => 'newuser@example.com']
    );

    $response = $this->get($signedUrl);

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/RegisterComplete')
        ->has('email')
        ->has('signedUrl')
    );
});

test('register success page renders correct component', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $response = $this->get(route('register.success'));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/RegisterSuccess')
    );
});
