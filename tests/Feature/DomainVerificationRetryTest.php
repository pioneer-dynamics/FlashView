<?php

use App\Exceptions\DnsVerificationPendingException;
use App\Jobs\RetryDomainVerification;
use App\Models\User;
use App\Notifications\DomainVerificationTimeoutNotification;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('retry job dispatched on first failed verify', function () {
    Queue::fake();

    $user = createPrimeUser();
    makeDomainIdentity($user);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $this->actingAs($user)->post(route('user.sender-identity.verify'));

    Queue::assertPushed(RetryDomainVerification::class);
    expect($user->fresh()->senderIdentity->verification_retry_dispatched_at)->not->toBeNull();
});

test('retry job not dispatched when retry already active', function () {
    Queue::fake();

    $user = createPrimeUser();
    makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $this->actingAs($user)
        ->post(route('user.sender-identity.verify'))
        ->assertSessionHasErrors(['domain' => "We're already working on verifying your domain in the background. You'll receive an email once it's done."]);

    Queue::assertNotPushed(RetryDomainVerification::class);
});

test('retry flag cleared on manual verify success', function () {
    Queue::fake();
    Notification::fake();

    $user = createPrimeUser();
    makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $this->actingAs($user)->post(route('user.sender-identity.verify'));

    expect($user->fresh()->senderIdentity->verification_retry_dispatched_at)->toBeNull();
});

test('domain change clears retry flag', function () {
    $user = createPrimeUser();
    makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $this->actingAs($user)->post(route('user.sender-identity.store'), [
        'type' => 'domain',
        'company_name' => 'Acme Corp',
        'domain' => 'new-acme.com',
    ]);

    expect($user->fresh()->senderIdentity->verification_retry_dispatched_at)->toBeNull();
});

test('switching to email type clears retry flag', function () {
    $user = createPrimeUser();
    makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $this->actingAs($user)->post(route('user.sender-identity.store'), ['type' => 'email']);

    expect($user->fresh()->senderIdentity->verification_retry_dispatched_at)->toBeNull();
});

test('job verifies and clears flag on success', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, [
        'verification_retry_dispatched_at' => now(),
    ]);

    $service = $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
    $job->handle($service);

    $identity->refresh();
    expect($identity->verified_at)->not->toBeNull();
    expect($identity->verification_retry_dispatched_at)->toBeNull();
    Notification::assertSentTo($user, DomainVerifiedNotification::class);
});

test('job throws on dns not found to trigger retry', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user);

    $service = $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $this->expectException(DnsVerificationPendingException::class);

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
    $job->handle($service);
});

test('job stops silently on token mismatch', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, [
        'verification_token' => 'new-token-after-domain-change',
        'verification_retry_dispatched_at' => now(),
    ]);

    $service = $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->never();
    });

    $job = new RetryDomainVerification($identity, 'old-token-at-dispatch', now()->addHours(24));
    $job->handle($service);

    Notification::assertNothingSent();
});

test('job stops silently when identity deleted', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user);
    $token = $identity->verification_token;
    $identity->delete();

    $service = $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verify')->never();
    });

    $job = new RetryDomainVerification($identity, $token, now()->addHours(24));
    $job->handle($service);

    Notification::assertNothingSent();
});

test('job stops silently when already verified', function () {
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

    expect($identity->fresh()->verification_retry_dispatched_at)->toBeNull();
    Notification::assertNothingSent();
});

test('failed callback clears flag and sends timeout notification', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
    $job->failed(new RuntimeException('timeout'));

    expect($identity->fresh()->verification_retry_dispatched_at)->toBeNull();
    Notification::assertSentTo($user, DomainVerificationTimeoutNotification::class);
});

test('failed callback does nothing if token changed', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user, [
        'verification_token' => 'new-token',
    ]);

    $job = new RetryDomainVerification($identity, 'old-token-at-dispatch', now()->addHours(24));
    $job->failed(new RuntimeException('timeout'));

    Notification::assertNothingSent();
});

test('failed callback does nothing if identity deleted', function () {
    Notification::fake();

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user);
    $token = $identity->verification_token;
    $identity->delete();

    $job = new RetryDomainVerification($identity, $token, now()->addHours(24));
    $job->failed(new RuntimeException('timeout'));

    Notification::assertNothingSent();
});

test('job has correct backoff schedule', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user);

    $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));

    expect($job->backoff())->toEqual([120, 240, 480, 960, 1920, 3840, 7680, 15360, 30720]);
});

test('job retry until returns provided deadline', function () {
    Carbon::setTestNow(now());

    $user = User::factory()->withPersonalTeam()->create();
    $identity = makeDomainIdentity($user);
    $deadline = now()->addHours(24);

    $job = new RetryDomainVerification($identity, $identity->verification_token, $deadline);

    expect($job->retryUntil())->toEqual($deadline);

    Carbon::setTestNow();
});
