<?php

use App\Http\Middleware\EnsureEnvironmentSubscriptionAllowed;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('non allowlisted user cannot subscribe on non production', function () {
    Config::set('access.enabled', true);
    Config::set('access.allowed_emails', ['allowed@gmail.com']);

    $plan = Plan::factory()->create();
    $user = User::factory()->create(['email' => 'blocked@gmail.com']);

    $response = $this->actingAs($user)->get(route('plans.subscribe', [$plan, 'monthly']));

    $response->assertStatus(403);
});

test('allowlisted user can initiate subscription on non production', function () {
    Config::set('access.enabled', true);
    Config::set('access.allowed_emails', ['allowed@gmail.com']);

    Route::middleware(['web', 'auth', EnsureEnvironmentSubscriptionAllowed::class])
        ->get('/test-env-subscription', fn () => 'ok');

    $user = User::factory()->create(['email' => 'allowed@gmail.com']);

    $response = $this->actingAs($user)->get('/test-env-subscription');

    $response->assertOk();
    $response->assertSee('ok');
});

test('subscription not restricted on production', function () {
    Config::set('access.enabled', false);

    Route::middleware(['web', 'auth', EnsureEnvironmentSubscriptionAllowed::class])
        ->get('/test-env-subscription-prod', fn () => 'ok');

    $user = User::factory()->create(['email' => 'anyone@gmail.com']);

    $response = $this->actingAs($user)->get('/test-env-subscription-prod');

    $response->assertOk();
    $response->assertSee('ok');
});

test('non allowlisted email cannot register on non production', function () {
    Config::set('access.enabled', true);
    Config::set('access.allowed_emails', ['allowed@gmail.com']);

    $response = $this->post('/register', [
        'email' => 'blocked@gmail.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('allowlisted email can register on non production', function () {
    Config::set('access.enabled', true);
    Config::set('access.allowed_emails', ['allowed@gmail.com']);

    $response = $this->post('/register', [
        'email' => 'allowed@gmail.com',
    ]);

    $response->assertSessionHasNoErrors();
});

test('registration not restricted on production', function () {
    Config::set('access.enabled', false);

    $response = $this->post('/register', [
        'email' => 'anyone@gmail.com',
    ]);

    $response->assertSessionHasNoErrors();
});

test('empty allowlist blocks all users on non production', function () {
    Config::set('access.enabled', true);
    Config::set('access.allowed_emails', []);

    $response = $this->post('/register', [
        'email' => 'anyone@gmail.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('allowlist check is case insensitive', function () {
    Config::set('access.enabled', true);
    Config::set('access.allowed_emails', ['allowed@gmail.com']);

    $response = $this->post('/register', [
        'email' => 'Allowed@gmail.com',
    ]);

    $response->assertSessionHasNoErrors();
});
