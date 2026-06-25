<?php

use App\Models\Plan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->plan = Plan::factory()->create([
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

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/config');

    $response->assertUnauthorized();
});

test('user without subscription gets 403', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/config');

    $response->assertForbidden();
});

test('config returns user specific limits', function () {
    subscribeUserToPlan($this->user, $this->plan);
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/config');

    $response->assertOk()
        ->assertJsonStructure([
            'expiry_options' => [
                ['label', 'value'],
            ],
            'max_expiry',
            'max_message_length',
        ])
        ->assertJsonPath('max_expiry', 43200)
        ->assertJsonPath('max_message_length', 100000);
});

test('config does not expose raw limits', function () {
    subscribeUserToPlan($this->user, $this->plan);
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/config');

    $response->assertOk()
        ->assertJsonMissing(['expiry_limits'])
        ->assertJsonMissing(['message_length' => config('secrets.message_length')])
        ->assertJsonMissing(['plan_limits']);
});

test('expiry options filtered to user max', function () {
    $limitedPlan = Plan::factory()->create([
        'name' => 'Basic',
        'stripe_monthly_price_id' => 'price_monthly_basic',
        'stripe_yearly_price_id' => 'price_yearly_basic',
        'stripe_product_id' => 'prod_basic',
        'price_per_month' => 10,
        'price_per_year' => 100,
        'features' => [
            'messages' => [
                'order' => 2,
                'label' => ':message_length character limit per message',
                'config' => ['message_length' => 500],
                'type' => 'feature',
            ],
            'expiry' => [
                'order' => 3,
                'label' => 'Maximum expiry of :expiry_label',
                'config' => ['expiry_label' => '1 day', 'expiry_minutes' => 1440],
                'type' => 'feature',
            ],
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => 'feature',
            ],
        ],
    ]);

    subscribeUserToPlan($this->user, $limitedPlan);
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/config');

    $response->assertOk()
        ->assertJsonPath('max_expiry', 1440)
        ->assertJsonPath('max_message_length', 500);

    $expiryValues = array_column($response->json('expiry_options'), 'value');

    // All returned options should be <= 1440 minutes (1 day)
    foreach ($expiryValues as $value) {
        expect($value)->toBeLessThanOrEqual(1440);
    }

    // Options beyond 1 day should not be present
    expect($expiryValues)->not->toContain(4320);
    expect($expiryValues)->not->toContain(10080);
});

test('full plan gets all expiry options', function () {
    subscribeUserToPlan($this->user, $this->plan);
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/config');

    $expiryValues = array_column($response->json('expiry_options'), 'value');

    expect($expiryValues)->toHaveCount(count(config('secrets.expiry_options')));
});
