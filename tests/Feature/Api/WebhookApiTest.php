<?php

use App\Models\Plan;
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
            'email_notification' => [
                'order' => 4.5,
                'label' => 'Email Notifications',
                'config' => ['email' => true],
                'type' => 'feature',
            ],
            'webhook_notification' => [
                'order' => 4.6,
                'label' => 'Webhook Notifications',
                'config' => ['webhook' => true],
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

test('can get webhook config', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->getJson('/api/v1/webhook');

    $response->assertOk()
        ->assertJsonStructure(['webhook_url', 'webhook_secret', 'configured']);
});

test('can update webhook url', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->putJson('/api/v1/webhook', [
        'webhook_url' => 'https://example.com/webhook',
    ]);

    $response->assertOk()
        ->assertJson([
            'webhook_url' => 'https://example.com/webhook',
            'configured' => true,
            'message' => 'Webhook settings updated.',
        ]);

    $this->user->refresh();
    expect($this->user->webhook_url)->toEqual('https://example.com/webhook');
    expect($this->user->webhook_secret)->not->toBeNull();
});

test('can regenerate webhook secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    $oldSecret = $this->user->webhook_secret;
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->postJson('/api/v1/webhook/regenerate-secret');

    $response->assertOk()
        ->assertJsonStructure(['webhook_secret', 'message']);

    $this->user->refresh();
    $this->assertNotEquals($oldSecret, $this->user->webhook_secret);
});

test('regenerate returns full secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->postJson('/api/v1/webhook/regenerate-secret');

    $newSecret = $response->json('webhook_secret');
    expect(strlen($newSecret))->toEqual(64);
    $this->assertStringNotContainsString('*', $newSecret);
});

test('cannot regenerate without webhook configured', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->postJson('/api/v1/webhook/regenerate-secret');

    $response->assertStatus(422);
});

test('can delete webhook', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => bin2hex(random_bytes(32)),
    ]);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->deleteJson('/api/v1/webhook');

    $response->assertOk()
        ->assertJson(['message' => 'Webhook configuration removed.']);

    $this->user->refresh();
    expect($this->user->webhook_url)->toBeNull();
    expect($this->user->webhook_secret)->toBeNull();
});

test('rejects token without webhook manage ability', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $response = $this->getJson('/api/v1/webhook');

    $response->assertStatus(403);
});

test('rejects user without api plan', function () {
    $freePlan = Plan::factory()->create([
        'name' => 'Free',
        'stripe_monthly_price_id' => 'price_monthly_free',
        'stripe_yearly_price_id' => 'price_yearly_free',
        'stripe_product_id' => 'prod_free',
        'price_per_month' => 0,
        'price_per_year' => 0,
        'features' => [
            'api' => ['order' => 6, 'label' => 'API', 'config' => [], 'type' => 'missing'],
        ],
    ]);

    subscribeUserToPlan($this->user, $freePlan);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->getJson('/api/v1/webhook');

    $response->assertStatus(403);
});

test('validation rejects non https url via api', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->putJson('/api/v1/webhook', [
        'webhook_url' => 'http://example.com/webhook',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('webhook_url');
});

test('show returns masked secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->updateQuietly([
        'webhook_url' => 'https://example.com/webhook',
        'webhook_secret' => 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
    ]);
    Sanctum::actingAs($this->user, ['webhook:manage']);

    $response = $this->getJson('/api/v1/webhook');

    $maskedSecret = $response->json('webhook_secret');
    $this->assertStringContainsString('*', $maskedSecret);
    expect($maskedSecret)->toEndWith('34567890');
});
