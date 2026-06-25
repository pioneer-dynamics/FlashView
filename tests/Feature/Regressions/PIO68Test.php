<?php

use App\Models\SenderIdentity;
use Database\Factories\SecretFactory;
use Laravel\Sanctum\Sanctum;

function buildPIO68EncryptedMessage(): string
{
    return (new SecretFactory)->generateEncryptedMessage(50);
}

test('cli secret carries verified sender identity when user has active badge', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'email' => null,
        'verification_token' => 'some-token',
        'verified_at' => now(),
    ]);

    Sanctum::actingAs($user, ['secrets:create']);

    $this->postJson('/api/v1/secrets', [
        'message' => buildPIO68EncryptedMessage(),
        'expires_in' => 1440,
        'include_sender_identity' => true,
    ])->assertStatus(201);

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toEqual('Acme Corp');
    expect($secret->sender_domain)->toEqual('acme.com');
    expect($secret->sender_email)->toBeNull();
});

test('cli email identity snapshot attached to secret', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'company_name' => null,
        'domain' => null,
        'verified_at' => now(),
    ]);

    Sanctum::actingAs($user, ['secrets:create']);

    $this->postJson('/api/v1/secrets', [
        'message' => buildPIO68EncryptedMessage(),
        'expires_in' => 1440,
        'include_sender_identity' => true,
    ])->assertStatus(201);

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toEqual($user->email);
});

test('cli unverified identity not applied', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'email' => null,
        'verification_token' => 'some-token',
        'verified_at' => null,
    ]);

    Sanctum::actingAs($user, ['secrets:create']);

    $this->postJson('/api/v1/secrets', [
        'message' => buildPIO68EncryptedMessage(),
        'expires_in' => 1440,
    ])->assertStatus(201);

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toBeNull();
});

test('cli user without sender identity plan gets no snapshot', function () {
    $user = createBasicUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    Sanctum::actingAs($user, ['secrets:create']);

    $this->postJson('/api/v1/secrets', [
        'message' => buildPIO68EncryptedMessage(),
        'expires_in' => 1440,
    ])->assertStatus(201);

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toBeNull();
});
