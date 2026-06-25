<?php

use App\Models\Plan;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function maskedEmailSecretPayload(int $plaintextLength = 50, int $expiresIn = 5): array
{
    return [
        'message' => (new SecretFactory)->generateEncryptedMessage($plaintextLength),
        'expires_in' => $expiresIn,
    ];
}

test('masked email stored when setting enabled', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $response = $this->actingAs($user)
        ->post(route('secret.store'), array_merge(maskedEmailSecretPayload(), [
            'email' => 'recipient@example.com',
        ]));

    $response->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->masked_recipient_email)->not->toBeNull();
    expect($secret->masked_recipient_email)->toStartWith('r');
    $this->assertStringNotContainsString('ecipient', $secret->masked_recipient_email);
    $this->assertStringNotContainsString('recipient@example.com', $secret->masked_recipient_email);
});

test('masked email not stored when setting disabled', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => false,
    ]);

    $response = $this->actingAs($user)
        ->post(route('secret.store'), array_merge(maskedEmailSecretPayload(), [
            'email' => 'recipient@example.com',
        ]));

    $response->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->masked_recipient_email)->toBeNull();
});

test('no masked email stored when no email provided', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $response = $this->actingAs($user)
        ->post(route('secret.store'), maskedEmailSecretPayload());

    $response->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->masked_recipient_email)->toBeNull();
});

test('masked email appears in secrets list', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $this->actingAs($user)
        ->post(route('secret.store'), array_merge(maskedEmailSecretPayload(), [
            'email' => 'recipient@example.com',
        ]));

    $response = $this->actingAs($user)->get(route('secrets.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Secret/Index')
        ->has('secrets.data.0.masked_recipient_email')
    );
});

test('masked email null in secrets list when no email provided', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $this->actingAs($user)->post(route('secret.store'), maskedEmailSecretPayload());

    $response = $this->actingAs($user)->get(route('secrets.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Secret/Index')
        ->where('secrets.data.0.masked_recipient_email', null)
    );
});

test('api stores masked email when setting enabled', function () {
    $plan = Plan::factory()->withApiAccess()->create();

    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => true,
    ]);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_mask_enabled',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    Sanctum::actingAs($user, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets', [
        'message' => (new SecretFactory)->generateEncryptedMessage(50),
        'expires_in' => 1440,
        'email' => 'recipient@example.com',
    ]);

    $response->assertStatus(201);

    $secret = $user->secrets()->first();
    expect($secret->masked_recipient_email)->not->toBeNull();
    $this->assertStringNotContainsString('recipient@example.com', $secret->masked_recipient_email);
});

test('api does not store masked email when setting disabled', function () {
    $plan = Plan::factory()->withApiAccess()->create();

    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => false,
    ]);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_mask_disabled',
        'stripe_status' => 'active',
        'stripe_price' => $plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    Sanctum::actingAs($user, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets', [
        'message' => (new SecretFactory)->generateEncryptedMessage(50),
        'expires_in' => 1440,
        'email' => 'recipient@example.com',
    ]);

    $response->assertStatus(201);

    $secret = $user->secrets()->first();
    expect($secret->masked_recipient_email)->toBeNull();
});

test('setting enabled after creation does not mask old secrets', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'store_masked_recipient_email' => false,
    ]);

    // Create without masking
    $this->actingAs($user)->post(route('secret.store'), array_merge(maskedEmailSecretPayload(), [
        'email' => 'recipient@example.com',
    ]));

    $secretBefore = $user->secrets()->first();
    expect($secretBefore->masked_recipient_email)->toBeNull();

    // Enable the setting — existing secret should remain unaffected
    $user->update(['store_masked_recipient_email' => true]);

    $secretAfter = $user->secrets()->first();
    expect($secretAfter->masked_recipient_email)->toBeNull();
});
