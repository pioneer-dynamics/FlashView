<?php

use App\Models\Plan;
use App\Models\Secret;
use App\Models\SenderIdentity;
use App\Models\User;

/**
 * Creates a user with API access (Prime plan with API feature, active subscription).
 */
function createUserWithApiAccess(): User
{
    $user = User::factory()->withPersonalTeam()->create();

    $plan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
    ]);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_api_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    return $user;
}

/**
 * Creates a subscribed user on a sender identity plan.
 */
function createPrimeUser(): User
{
    $plan = Plan::factory()->withSenderIdentity()->create();
    $user = User::factory()->withPersonalTeam()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_prime_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    return $user;
}

/**
 * Creates a subscribed user on a basic plan (no sender identity).
 */
function createBasicUser(): User
{
    $plan = Plan::factory()->create();
    $user = User::factory()->withPersonalTeam()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_basic_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    return $user;
}

/**
 * Subscribes a user to a plan (creates a Cashier subscription).
 */
function subscribeUserToPlan(User $user, Plan $plan): void
{
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->word(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
}

/**
 * Creates a Cashier subscription for a user (no quantity field variant).
 */
function createSubscriptionForUser(User $user, Plan $plan): void
{
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
    ]);
}

/**
 * Creates an active secret (not expired, with message content).
 */
function createActiveSecret(?User $user = null): Secret
{
    return Secret::withoutGlobalScopes()->create([
        'message' => 'test-secret-message',
        'user_id' => $user?->id,
        'expires_at' => now()->addHour(),
    ]);
}

/**
 * Creates a secret belonging to a user, with optional attribute overrides.
 */
function createSecretForUser(User $user, array $overrides = []): Secret
{
    return Secret::withoutEvents(fn () => Secret::forceCreate(array_merge([
        'message' => encrypt('test-encrypted-message'),
        'expires_at' => now()->addDay(),
        'user_id' => $user->id,
        'ip_address_sent' => encrypt('127.0.0.1', false),
    ], $overrides)));
}

/**
 * Creates a domain-type SenderIdentity for a user with optional attribute overrides.
 */
function makeDomainIdentity(User $user, array $attrs = []): SenderIdentity
{
    return SenderIdentity::factory()->for($user)->create(array_merge([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test-abc',
        'verified_at' => null,
        'verification_retry_dispatched_at' => null,
    ], $attrs));
}
