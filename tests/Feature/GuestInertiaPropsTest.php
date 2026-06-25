<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('guest does not have auth user in inertia props', function () {
    $response = $this->get('/');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.user', null)
    );
});

test('authenticated user has full user data in inertia props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('auth.user.id')
        ->has('auth.user.name')
        ->has('auth.user.email')
        ->where('auth.user.id', $user->id)
    );
});
