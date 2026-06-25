<?php

use App\Models\Plan;
use App\Models\Secret;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->primePlan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => [
            'messages' => [
                'order' => 2,
                'label' => ':message_length character limit per message',
                'config' => ['message_length' => 100000],
                'type' => 'feature',
            ],
            'expiry' => [
                'order' => 3,
                'label' => 'Maximum expiry of :expiry_label',
                'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200],
                'type' => 'feature',
            ],
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => ['email' => true],
                'type' => 'feature',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => ['webhook' => false],
                'type' => 'missing',
            ],
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => 'feature',
            ],
        ],
    ]);

    $this->user = User::factory()->withPersonalTeam()->create();
});

test('can show own secret metadata', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['hash_id', 'expires_at', 'created_at', 'is_expired', 'is_retrieved', 'retrieved_at'],
        ])
        ->assertJson([
            'data' => [
                'hash_id' => $secret->hash_id,
                'is_expired' => false,
                'is_retrieved' => false,
            ],
        ]);
});

test('cannot show other users secret metadata', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $otherUser = User::factory()->withPersonalTeam()->create();
    $secret = createSecretForUser($otherUser);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertForbidden();
});

test('show returns 403 for nonexistent secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $response = $this->getJson('/api/v1/secrets/invalidhashid');

    $response->assertForbidden();
});

test('show requires list ability', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $secret = createSecretForUser($this->user);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertForbidden();
});

test('show does not mark secret as retrieved', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $this->getJson("/api/v1/secrets/{$secret->hash_id}");

    $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($freshSecret->message)->not->toBeNull();
    expect($freshSecret->retrieved_at)->toBeNull();
});

test('show works for expired secrets', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user, [
        'expires_at' => now()->subDay(),
        'message' => null,
    ]);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'hash_id' => $secret->hash_id,
                'is_expired' => true,
            ],
        ]);
});

test('show works for retrieved secrets', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user, [
        'retrieved_at' => now()->subHour(),
        'message' => null,
    ]);

    $response = $this->getJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'hash_id' => $secret->hash_id,
                'is_retrieved' => true,
            ],
        ]);
});
