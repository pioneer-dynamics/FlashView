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

/**
 * Build a fake encrypted message that passes MessageLength validation.
 * Format: 16-char salt + base64(28-byte header + plaintext)
 */
function buildEncryptedMessage(string $plaintext = 'hello'): string
{
    $salt = str_repeat('a', 16);
    $header = str_repeat("\0", 28);
    $body = $header.$plaintext;

    return $salt.base64_encode($body);
}

test('can create secret via api', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets', [
        'message' => buildEncryptedMessage('test secret message'),
        'expires_in' => 1440,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['hash_id', 'url', 'expires_at', 'created_at'],
        ]);

    $this->assertDatabaseHas('secrets', [
        'user_id' => $this->user->id,
    ]);

    $this->assertStringContainsString('signature=', $response->json('data.url'));
});

test('store validates required fields', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['message', 'expires_in']);
});

test('store validates invalid expiry', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets', [
        'message' => buildEncryptedMessage('test'),
        'expires_in' => 999999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['expires_in']);
});

test('can list own secrets', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    createSecretForUser($this->user);
    createSecretForUser($this->user);

    $response = $this->getJson('/api/v1/secrets');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['hash_id', 'expires_at', 'created_at', 'is_expired', 'is_retrieved'],
            ],
            'links',
            'meta',
        ]);
});

test('cannot see other users secrets', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $otherUser = User::factory()->withPersonalTeam()->create();
    createSecretForUser($otherUser);
    createSecretForUser($this->user);

    $response = $this->getJson('/api/v1/secrets');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('index includes expired and retrieved secrets', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    createSecretForUser($this->user);
    createSecretForUser($this->user, [
        'expires_at' => now()->subDay(),
        'message' => null,
        'retrieved_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/v1/secrets');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('index does not burn secrets', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $secret = createSecretForUser($this->user);

    $this->getJson('/api/v1/secrets');

    $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($freshSecret->message)->not->toBeNull();
    expect($freshSecret->retrieved_at)->toBeNull();
});

test('can burn own secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:delete']);

    $secret = createSecretForUser($this->user);

    $response = $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertOk()
        ->assertJson(['message' => 'Secret burned successfully.']);

    $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($freshSecret->message)->toBeNull();
    expect($freshSecret->retrieved_at)->not->toBeNull();
});

test('cannot burn other users secret', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:delete']);

    $otherUser = User::factory()->withPersonalTeam()->create();
    $secret = createSecretForUser($otherUser);

    $response = $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertForbidden();
});

test('destroy does not trigger retrieval event', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:delete']);

    $secret = createSecretForUser($this->user);

    $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

    $freshSecret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($secret->id));
    expect($freshSecret->retrieved_at)->not->toBeNull();
});

test('store requires create ability', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $response = $this->postJson('/api/v1/secrets', [
        'message' => buildEncryptedMessage('test'),
        'expires_in' => 1440,
    ]);

    $response->assertForbidden();
});

test('index requires list ability', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $response = $this->getJson('/api/v1/secrets');

    $response->assertForbidden();
});

test('destroy requires delete ability', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    Sanctum::actingAs($this->user, ['secrets:create']);

    $secret = createSecretForUser($this->user);

    $response = $this->deleteJson("/api/v1/secrets/{$secret->hash_id}");

    $response->assertForbidden();
});

test('unauthenticated requests are rejected', function () {
    $response = $this->getJson('/api/v1/secrets');

    $response->assertUnauthorized();
});

test('user without subscription gets 403', function () {
    Sanctum::actingAs($this->user, ['secrets:list']);

    $response = $this->getJson('/api/v1/secrets');

    $response->assertForbidden()
        ->assertJson(['message' => 'API access requires an active subscription with API support.']);
});

test('user with non api plan gets 403', function () {
    $basicPlan = Plan::factory()->create([
        'name' => 'Basic',
        'stripe_monthly_price_id' => 'price_monthly_basic',
        'stripe_yearly_price_id' => 'price_yearly_basic',
        'stripe_product_id' => 'prod_basic',
        'price_per_month' => 25,
        'price_per_year' => 250,
        'features' => [
            'api' => [
                'order' => 6,
                'label' => 'API Access',
                'config' => [],
                'type' => 'missing',
            ],
        ],
    ]);

    subscribeUserToPlan($this->user, $basicPlan);
    Sanctum::actingAs($this->user, ['secrets:list']);

    $response = $this->getJson('/api/v1/secrets');

    $response->assertForbidden()
        ->assertJson(['message' => 'Your current plan does not include API access. Please upgrade to a plan with API support.']);
});

test('tokens revoked when subscription cancelled', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->createToken('test-token', ['secrets:create']);
    expect($this->user->fresh()->tokens)->toHaveCount(1);

    $subscription = $this->user->subscriptions()->first();
    $subscription->update([
        'stripe_status' => 'canceled',
        'ends_at' => now(),
    ]);

    expect($this->user->fresh()->tokens)->toHaveCount(0);
});

test('tokens revoked when plan downgraded', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->createToken('test-token', ['secrets:create']);
    expect($this->user->fresh()->tokens)->toHaveCount(1);

    $basicPlan = Plan::factory()->create([
        'name' => 'Basic',
        'stripe_monthly_price_id' => 'price_monthly_basic_2',
        'stripe_yearly_price_id' => 'price_yearly_basic_2',
        'stripe_product_id' => 'prod_basic_2',
        'price_per_month' => 25,
        'price_per_year' => 250,
        'features' => [
            'api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'missing'],
        ],
    ]);

    $subscription = $this->user->subscriptions()->first();
    $subscription->update([
        'stripe_price' => $basicPlan->stripe_monthly_price_id,
    ]);

    expect($this->user->fresh()->tokens)->toHaveCount(0);
});

test('tokens kept when plan still has api access', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->user->createToken('test-token', ['secrets:create']);
    expect($this->user->fresh()->tokens)->toHaveCount(1);

    $subscription = $this->user->subscriptions()->first();
    $subscription->update([
        'stripe_price' => $this->primePlan->stripe_yearly_price_id,
    ]);

    expect($this->user->fresh()->tokens)->toHaveCount(1);
});

test('token management page accessible with api plan', function () {
    subscribeUserToPlan($this->user, $this->primePlan);
    $this->actingAs($this->user);

    $response = $this->get('/user/api-tokens');

    $response->assertOk();
});

test('token management page blocked without api plan', function () {
    $this->actingAs($this->user);

    $response = $this->get('/user/api-tokens');

    $response->assertForbidden();
});
