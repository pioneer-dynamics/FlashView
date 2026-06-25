<?php

use App\Jobs\RetryDomainVerification;
use App\Models\SenderIdentity;
use App\Models\User;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Database\Factories\SecretFactory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function senderIdentitySecretPayload(int $expiresIn = 5): array
{
    return [
        'message' => (new SecretFactory)->generateEncryptedMessage(50),
        'expires_in' => $expiresIn,
    ];
}

test('guest cannot store sender identity', function () {
    $this->post(route('user.sender-identity.store'), ['type' => 'email'])
        ->assertRedirect(route('login'));
});

test('unsubscribed user gets 403 on sender identity store', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), ['type' => 'email'])
        ->assertForbidden();
});

test('basic plan user gets 403 on sender identity store', function () {
    $user = createBasicUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), ['type' => 'email'])
        ->assertForbidden();
});

test('unsubscribed user gets 403 on verify', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->post(route('user.sender-identity.verify'))
        ->assertForbidden();
});

test('unsubscribed user gets 403 on destroy', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $this->actingAs($user)
        ->delete(route('user.sender-identity.destroy'))
        ->assertForbidden();
});

test('prime user can create email identity', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), ['type' => 'email'])
        ->assertRedirect();

    $identity = $user->fresh()->senderIdentity;
    expect($identity)->not->toBeNull();
    expect($identity->type)->toEqual('email');
    expect($identity->email)->toEqual($user->email);
    expect($identity->verified_at)->not->toBeNull();
    expect($identity->domain)->toBeNull();
    expect($identity->company_name)->toBeNull();
    expect($identity->verification_token)->toBeNull();
});

test('email identity is immediately verified', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), ['type' => 'email']);

    expect($user->fresh()->hasVerifiedSenderIdentity())->toBeTrue();
});

test('prime user can create domain identity', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
        ])
        ->assertRedirect();

    $identity = $user->fresh()->senderIdentity;
    expect($identity)->not->toBeNull();
    expect($identity->type)->toEqual('domain');
    expect($identity->company_name)->toEqual('Acme Corp');
    expect($identity->domain)->toEqual('acme.com');
    expect($identity->verification_token)->not->toBeNull();
    expect($identity->verified_at)->toBeNull();
    expect($identity->email)->toBeNull();
});

test('domain identity is not immediately verified', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
        ]);

    expect($user->fresh()->hasVerifiedSenderIdentity())->toBeFalse();
});

test('domain identity requires company name', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'domain' => 'acme.com',
        ])
        ->assertSessionHasErrors('company_name');
});

test('domain identity requires valid domain format', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'not a domain',
        ])
        ->assertSessionHasErrors('domain');
});

test('email field is prohibited in store request', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'email',
            'email' => 'attacker@evil.com',
        ])
        ->assertSessionHasErrors('email');
});

test('updating domain with new domain resets verification', function () {
    $user = createPrimeUser();
    $identity = SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'old-token',
        'verified_at' => now(),
        'verification_retry_dispatched_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'new-acme.com',
        ]);

    $identity->refresh();
    expect($identity->domain)->toEqual('new-acme.com');
    expect($identity->verified_at)->toBeNull();
    $this->assertNotEquals('old-token', $identity->verification_token);
    expect($identity->verification_retry_dispatched_at)->toBeNull();
});

test('updating domain without change keeps verification', function () {
    $user = createPrimeUser();
    $verifiedAt = now()->subDay();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'existing-token',
        'verified_at' => $verifiedAt,
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
        ]);

    $identity = $user->fresh()->senderIdentity;
    expect($identity->verified_at)->not->toBeNull();
    expect($identity->verification_token)->toEqual('existing-token');
});

test('switching from email to domain resets verification', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
        ]);

    $identity = $user->fresh()->senderIdentity;
    expect($identity->type)->toEqual('domain');
    expect($identity->verified_at)->toBeNull();
    expect($identity->verification_token)->not->toBeNull();
});

test('switching from domain to email verifies immediately', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'some-token',
        'verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), ['type' => 'email']);

    $identity = $user->fresh()->senderIdentity;
    expect($identity->type)->toEqual('email');
    expect($identity->verified_at)->not->toBeNull();
    expect($identity->domain)->toBeNull();
    expect($identity->verification_token)->toBeNull();
});

test('domain verification succeeds when dns record found', function () {
    Notification::fake();
    Queue::fake();

    $user = createPrimeUser();
    $identity = SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test-token',
        'verified_at' => null,
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $this->actingAs($user)
        ->post(route('user.sender-identity.verify'))
        ->assertRedirect();

    expect($identity->fresh()->verified_at)->not->toBeNull();
    expect($user->fresh()->hasVerifiedSenderIdentity())->toBeTrue();
    Notification::assertSentTo($user, DomainVerifiedNotification::class);
    Queue::assertNotPushed(RetryDomainVerification::class);
});

test('domain verification fails when dns record not found', function () {
    Queue::fake();

    $user = createPrimeUser();
    $identity = SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test-token',
        'verified_at' => null,
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $this->actingAs($user)
        ->post(route('user.sender-identity.verify'))
        ->assertSessionHasErrors('domain');

    expect($identity->fresh()->verified_at)->toBeNull();
    Queue::assertPushed(RetryDomainVerification::class);
    expect($identity->fresh()->verification_retry_dispatched_at)->not->toBeNull();
});

test('verify fails when no domain identity configured', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->post(route('user.sender-identity.verify'))
        ->assertSessionHasErrors('domain');
});

test('verify fails when identity is email type', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.verify'))
        ->assertSessionHasErrors('domain');
});

test('prime user can delete sender identity', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('user.sender-identity.destroy'))
        ->assertRedirect();

    expect($user->fresh()->senderIdentity)->toBeNull();
});

test('destroy is no op when no identity exists', function () {
    $user = createPrimeUser();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('user.sender-identity.destroy'))
        ->assertRedirect();
});

test('changing company name on verified identity resets verification', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'old-token',
        'verified_at' => now(),
        'verification_retry_dispatched_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Microsoft Corporation',
            'domain' => 'acme.com',
        ]);

    $identity = $user->fresh()->senderIdentity;
    expect($identity->verified_at)->toBeNull();
    $this->assertNotEquals('old-token', $identity->verification_token);
});

test('changing company name on unverified identity does not change token', function () {
    $user = createPrimeUser();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'existing-token',
        'verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp Updated',
            'domain' => 'acme.com',
        ]);

    $identity = $user->fresh()->senderIdentity;
    expect($identity->verified_at)->toBeNull();
    expect($identity->verification_token)->toEqual('existing-token');
});
