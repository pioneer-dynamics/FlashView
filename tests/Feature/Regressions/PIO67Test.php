<?php

namespace Tests\Feature\Regressions;

use App\Http\Middleware\EnsureEnvironmentSubscriptionAllowed;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PIO-67: Non-production environments use Stripe test payment gateway,
 * allowing anyone to register and subscribe using test cards to gain
 * paid-feature access for free.
 */
class PIO67Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Core regression: a user with a non-allowlisted email can freely
     * subscribe to a paid plan on a non-production environment.
     */
    public function test_non_allowlisted_user_cannot_subscribe_on_non_production(): void
    {
        Config::set('access.enabled', true);
        Config::set('access.allowed_emails', ['allowed@gmail.com']);

        $plan = Plan::factory()->create();
        $user = User::factory()->create(['email' => 'blocked@gmail.com']);

        $response = $this->actingAs($user)->get(route('plans.subscribe', [$plan, 'monthly']));

        $response->assertStatus(403);
    }

    public function test_allowlisted_user_can_initiate_subscription_on_non_production(): void
    {
        Config::set('access.enabled', true);
        Config::set('access.allowed_emails', ['allowed@gmail.com']);

        Route::middleware(['web', 'auth', EnsureEnvironmentSubscriptionAllowed::class])
            ->get('/test-env-subscription', fn () => 'ok');

        $user = User::factory()->create(['email' => 'allowed@gmail.com']);

        $response = $this->actingAs($user)->get('/test-env-subscription');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_subscription_not_restricted_on_production(): void
    {
        Config::set('access.enabled', false);

        Route::middleware(['web', 'auth', EnsureEnvironmentSubscriptionAllowed::class])
            ->get('/test-env-subscription-prod', fn () => 'ok');

        $user = User::factory()->create(['email' => 'anyone@gmail.com']);

        $response = $this->actingAs($user)->get('/test-env-subscription-prod');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_non_allowlisted_email_cannot_register_on_non_production(): void
    {
        Config::set('access.enabled', true);
        Config::set('access.allowed_emails', ['allowed@gmail.com']);

        $response = $this->post('/register', [
            'email' => 'blocked@gmail.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_allowlisted_email_can_register_on_non_production(): void
    {
        Config::set('access.enabled', true);
        Config::set('access.allowed_emails', ['allowed@gmail.com']);

        $response = $this->post('/register', [
            'email' => 'allowed@gmail.com',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_registration_not_restricted_on_production(): void
    {
        Config::set('access.enabled', false);

        $response = $this->post('/register', [
            'email' => 'anyone@gmail.com',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_empty_allowlist_blocks_all_users_on_non_production(): void
    {
        Config::set('access.enabled', true);
        Config::set('access.allowed_emails', []);

        $response = $this->post('/register', [
            'email' => 'anyone@gmail.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_allowlist_check_is_case_insensitive(): void
    {
        Config::set('access.enabled', true);
        Config::set('access.allowed_emails', ['allowed@gmail.com']);

        $response = $this->post('/register', [
            'email' => 'Allowed@gmail.com',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
