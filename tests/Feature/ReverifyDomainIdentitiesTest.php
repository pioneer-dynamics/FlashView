<?php

use App\Models\SenderIdentity;
use App\Models\User;
use App\Notifications\DomainLapsedNotification;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('verified identity older than 3 months with passing dns is refreshed', function () {
    $user = User::factory()->create();
    $oldVerifiedAt = now()->subMonths(4);
    $identity = SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test',
        'verified_at' => $oldVerifiedAt,
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $this->artisan('sender-identity:reverify')->assertSuccessful();

    $identity->refresh();
    expect($identity->verified_at)->not->toBeNull();
    expect($identity->verified_at->isAfter($oldVerifiedAt))->toBeTrue();
});

test('verified identity older than 3 months with failing dns is nulled', function () {
    Notification::fake();

    $user = User::factory()->create();
    $identity = SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test',
        'verified_at' => now()->subMonths(4),
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $this->artisan('sender-identity:reverify')->assertSuccessful();

    expect($identity->fresh()->verified_at)->toBeNull();
    Notification::assertSentTo($user, DomainLapsedNotification::class);
});

test('verified identity younger than 3 months is skipped', function () {
    $user = User::factory()->create();
    $verifiedAt = now()->subMonths(2);
    $identity = SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test',
        'verified_at' => $verifiedAt,
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->never();
    });

    $this->artisan('sender-identity:reverify')->assertSuccessful();

    $identity->refresh();
    expect($identity->verified_at)->not->toBeNull();
});

test('email type identities are never queried', function () {
    $user = User::factory()->create();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'email',
        'email' => $user->email,
        'verified_at' => now()->subMonths(4),
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->never();
    });

    $this->artisan('sender-identity:reverify')->assertSuccessful();
});

test('unverified domain identities are skipped', function () {
    $user = User::factory()->create();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test',
        'verified_at' => null,
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->never();
    });

    $this->artisan('sender-identity:reverify')->assertSuccessful();
});

test('command outputs pass and fail counts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    SenderIdentity::factory()->for($user1)->create([
        'type' => 'domain',
        'domain' => 'passing.com',
        'verification_token' => 'token1',
        'verified_at' => now()->subMonths(4),
    ]);

    SenderIdentity::factory()->for($user2)->create([
        'type' => 'domain',
        'domain' => 'failing.com',
        'verification_token' => 'token2',
        'verified_at' => now()->subMonths(4),
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')
            ->twice()
            ->andReturnUsing(fn ($identity) => $identity->domain === 'passing.com');
    });

    $this->artisan('sender-identity:reverify')
        ->expectsOutput('Re-verification complete: 1 passed, 1 failed.')
        ->assertSuccessful();
});
