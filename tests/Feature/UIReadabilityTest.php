<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('welcome page returns 200', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('welcome page renders secret form', function () {
    $response = $this->get('/');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Welcome')
    );
});

test('plans page returns 200', function () {
    $response = $this->get('/plans');

    $response->assertStatus(200);
});

test('plans page renders correct component', function () {
    $response = $this->get('/plans');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Plan/Index')
    );
});

test('faq page returns 200', function () {
    $response = $this->get('/faq');

    $response->assertStatus(200);
});

test('about page returns 200', function () {
    $response = $this->get('/about');

    $response->assertStatus(200);
});

test('dashboard returns 200 for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});

test('secrets index returns 200 for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('secrets.index'));

    $response->assertStatus(200);
});

test('secrets index renders correct component', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('secrets.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Secret/Index')
    );
});
