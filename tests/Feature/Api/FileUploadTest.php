<?php

use App\Models\Plan;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::factory()->create([
        'name' => 'Prime',
        'stripe_monthly_price_id' => 'price_monthly_prime',
        'stripe_yearly_price_id' => 'price_yearly_prime',
        'stripe_product_id' => 'prod_prime',
        'price_per_month' => 50,
        'price_per_year' => 500,
        'features' => [
            'expiry' => [
                'order' => 3,
                'label' => 'Maximum expiry of :expiry_label',
                'config' => ['expiry_label' => '30 days', 'expiry_minutes' => 43200],
                'type' => 'feature',
            ],
            'api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature'],
        ],
    ]);

    $this->user = User::factory()->withPersonalTeam()->create();

    $this->user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->word(),
        'stripe_status' => 'active',
        'stripe_price' => $this->plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);
});

test('prepare returns upload url and token', function () {
    Storage::fake();

    Sanctum::actingAs($this->user, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets/file/prepare');

    $response->assertOk()
        ->assertJsonStructure(['upload_type', 'upload_url', 'upload_headers', 'token']);

    $token = $response->json('token');
    $pending = Cache::get("pending_file_upload:{$token}");
    expect($pending)->not->toBeNull();
    expect($pending['user_id'])->toEqual($this->user->id);
    expect($pending['filepath'])->toStartWith('secrets/');
});

test('unauthenticated cannot prepare', function () {
    $response = $this->postJson('/api/v1/secrets/file/prepare');

    $response->assertUnauthorized();
});

test('store with file token creates secret', function () {
    Storage::fake();

    Sanctum::actingAs($this->user, ['secrets:create']);

    $filepath = 'secrets/'.Str::uuid().'.bin';
    $token = Str::uuid()->toString();

    Cache::put("pending_file_upload:{$token}", [
        'filepath' => $filepath,
        'user_id' => $this->user->id,
    ], now()->addMinutes(30));

    Storage::put($filepath, 'fake-encrypted-content');

    $response = $this->postJson('/api/v1/secrets', [
        'file_token' => $token,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 512,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['hash_id', 'url']]);
});

test('file token from different user is rejected via api', function () {
    Storage::fake();

    $attacker = User::factory()->withPersonalTeam()->create();
    $attacker->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->word(),
        'stripe_status' => 'active',
        'stripe_price' => $this->plan->stripe_monthly_price_id,
        'quantity' => 1,
    ]);

    $filepath = 'secrets/'.Str::uuid().'.bin';
    $token = Str::uuid()->toString();

    Cache::put("pending_file_upload:{$token}", [
        'filepath' => $filepath,
        'user_id' => $this->user->id,
    ], now()->addMinutes(30));

    Storage::put($filepath, 'fake-content');

    Sanctum::actingAs($attacker, ['secrets:create']);

    $response = $this->postJson('/api/v1/secrets', [
        'file_token' => $token,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 512,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ]);

    $response->assertStatus(422);
});

test('file token cannot be reused via api', function () {
    Storage::fake();

    $filepath = 'secrets/'.Str::uuid().'.bin';
    $token = Str::uuid()->toString();

    Cache::put("pending_file_upload:{$token}", [
        'filepath' => $filepath,
        'user_id' => $this->user->id,
    ], now()->addMinutes(30));

    Storage::put($filepath, 'fake-content');

    $payload = [
        'file_token' => $token,
        'file_original_name' => (new SecretFactory)->generateEncryptedMessage(20),
        'file_size' => 512,
        'file_mime_type' => 'application/pdf',
        'expires_in' => 60,
    ];

    Sanctum::actingAs($this->user, ['secrets:create']);
    $this->postJson('/api/v1/secrets', $payload)->assertStatus(201);

    Sanctum::actingAs($this->user, ['secrets:create']);
    $this->postJson('/api/v1/secrets', $payload)->assertStatus(422);
});
