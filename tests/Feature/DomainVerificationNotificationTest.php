<?php

use App\Jobs\RetryDomainVerification;
use App\Models\SenderIdentity;
use App\Models\User;
use App\Notifications\DomainLapsedNotification;
use App\Notifications\DomainVerificationTimeoutNotification;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Illuminate\Support\Facades\Notification;

test('domain verified notification sent on manual success', function () {
    Notification::fake();

    $user = createPrimeUser();
    makeDomainIdentity($user);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $this->actingAs($user)->post(route('user.sender-identity.verify'));

    Notification::assertSentTo($user, DomainVerifiedNotification::class);
});

test('domain verified notification sent on job success', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $service = $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
    $job->handle($service);

    Notification::assertSentTo($user, DomainVerifiedNotification::class);
});

test('domain verification timeout notification sent on exhaustion', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
    $job->failed(new RuntimeException('retryUntil exceeded'));

    Notification::assertSentTo($user, DomainVerificationTimeoutNotification::class);
    Notification::assertNotSentTo($user, DomainVerifiedNotification::class);
});

test('domain lapsed notification sent by reverify command', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    SenderIdentity::factory()->for($user)->create([
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'acme.com',
        'verification_token' => 'flashview-verification-test-abc',
        'verified_at' => now()->subMonths(4), // older than 3-month threshold
    ]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $this->artisan('sender-identity:reverify');

    Notification::assertSentTo($user, DomainLapsedNotification::class);
});

test('no notification sent when already verified guard triggers', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, [
        'verified_at' => now(),
        'verification_retry_dispatched_at' => now(),
    ]);

    $service = $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->never();
    });

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
    $job->handle($service);

    Notification::assertNothingSent();
});
