<?php

use App\Http\Middleware\Subscribed;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('x frame options header is set', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'DENY');
});

test('content security policy frame ancestors header is set', function () {
    $response = $this->get('/');

    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

test('security headers are set on api routes', function () {
    $response = $this->getJson('/api/user');

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

test('subscribed middleware redirects unsubscribed user', function () {
    Route::middleware(['web', 'auth', Subscribed::class])
        ->get('/test-subscribed', fn () => 'ok');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/test-subscribed');

    $response->assertRedirect('/billing');
});

test('subscribed middleware allows subscribed user', function () {
    Route::middleware(['web', 'auth', Subscribed::class])
        ->get('/test-subscribed', fn () => 'ok');

    $plan = Plan::factory()->withApiAccess()->create();
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_middleware',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get('/test-subscribed');

    $response->assertOk();
    $response->assertSee('ok');
});

test('trust proxies normalizes forwarded proto header', function () {
    Route::middleware('web')
        ->get('/test-proto', fn () => request()->isSecure() ? 'secure' : 'insecure');

    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https,http',
    ])->get('/test-proto');

    $response->assertOk();
    $response->assertSee('secure');
});
