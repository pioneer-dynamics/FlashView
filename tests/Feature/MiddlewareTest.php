<?php

namespace Tests\Feature;

use App\Http\Middleware\Subscribed;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_x_frame_options_header_is_set(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_subscribed_middleware_redirects_unsubscribed_user(): void
    {
        Route::middleware(['web', 'auth', Subscribed::class])
            ->get('/test-subscribed', fn () => 'ok');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/test-subscribed');

        $response->assertRedirect('/billing');
    }

    public function test_subscribed_middleware_allows_subscribed_user(): void
    {
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
    }

    public function test_trust_proxies_normalizes_forwarded_proto_header(): void
    {
        Route::middleware('web')
            ->get('/test-proto', fn () => request()->isSecure() ? 'secure' : 'insecure');

        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https,http',
        ])->get('/test-proto');

        $response->assertOk();
        $response->assertSee('secure');
    }
}
