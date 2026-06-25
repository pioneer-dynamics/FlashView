<?php

use App\Models\Secret;
use App\Models\SenderIdentity;
use Database\Factories\SecretFactory;

function snapshotSecretPayload(int $expiresIn = 5, bool $includeSenderIdentity = false): array
{
    return [
        'message' => (new SecretFactory)->generateEncryptedMessage(50),
        'expires_in' => $expiresIn,
        'include_sender_identity' => $includeSenderIdentity,
    ];
}

test('domain identity snapshot attached to secret', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'some-token',
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('secret.store'), snapshotSecretPayload(includeSenderIdentity: true))
        ->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toEqual('Acme Corp');
    expect($secret->sender_domain)->toEqual('acme.com');
    expect($secret->sender_email)->toBeNull();
});

test('email identity snapshot attached to secret', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('secret.store'), snapshotSecretPayload(includeSenderIdentity: true))
        ->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toEqual($user->email);
});

test('unverified domain identity not attached to secret', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'some-token',
        'verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('secret.store'), snapshotSecretPayload())
        ->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toBeNull();
});

test('guest secret has no sender snapshot', function () {
    $this->post(route('secret.store'), snapshotSecretPayload())
        ->assertSessionHasNoErrors();

    $secret = Secret::withoutGlobalScopes()->latest()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toBeNull();
});

test('expired plan user with verified identity gets no snapshot', function () {
    $user = createBasicUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('secret.store'), snapshotSecretPayload())
        ->assertSessionHasNoErrors();

    $secret = $user->secrets()->first();
    expect($secret->sender_company_name)->toBeNull();
    expect($secret->sender_domain)->toBeNull();
    expect($secret->sender_email)->toBeNull();
});

test('removing identity does not affect existing secrets', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('secret.store'), snapshotSecretPayload(includeSenderIdentity: true));

    $secretBefore = $user->secrets()->first();
    $senderEmailBefore = $secretBefore->sender_email;

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('user.sender-identity.destroy'));

    $secretBefore->refresh();
    expect($secretBefore->sender_email)->toEqual($senderEmailBefore);
});

test('show passes domain sender props', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'some-token',
        'verified_at' => now(),
    ]);

    $storeResponse = $this->actingAs($user)
        ->post(route('secret.store'), snapshotSecretPayload());

    $storeResponse->assertSessionHas('flash.secret.url');
    $url = $storeResponse->getSession()->get('flash')['secret']['url'];

    $response = $this->get($url);
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('senderCompanyName')
        ->has('senderDomain')
        ->has('senderEmail')
    );
});

test('show passes null sender props when no identity on secret', function () {
    $storeResponse = $this->post(route('secret.store'), snapshotSecretPayload());

    $storeResponse->assertSessionHas('flash.secret.url');
    $url = $storeResponse->getSession()->get('flash')['secret']['url'];

    $response = $this->get($url);
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('senderCompanyName', null)
        ->where('senderDomain', null)
        ->where('senderEmail', null)
    );
});

test('settings page passes plan supports sender identity true for prime', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->get(route('user.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->where('planSupportsSenderIdentity', true)
        );
});

test('settings page passes plan supports sender identity false for basic', function () {
    $user = createBasicUser();

    $this->actingAs($user)
        ->get(route('user.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->where('planSupportsSenderIdentity', false)
        );
});

test('settings page passes sender identity data when configured', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('user.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->has('senderIdentity')
            ->where('senderIdentity.type', 'email')
            ->where('senderIdentity.is_verified', true)
        );
});

test('settings page passes null sender identity when not configured', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->get(route('user.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->where('senderIdentity', null)
        );
});
