<?php

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

$lockerSeq = 0;

beforeEach(function () use (&$lockerSeq) {
    $lockerSeq++;
    $this->ip = '10.1.1.'.$lockerSeq;
    $this->accountId = str_pad((string) $lockerSeq, 10, '0', STR_PAD_LEFT);
    $this->lockerSeq = $lockerSeq;
    $this->rateLimiterKeys = [];

    $this->trackKey = function (string $key): void {
        $this->rateLimiterKeys[] = $key;
    };

    $this->ipKey = function (): string {
        return 'locker-ip:'.$this->ip;
    };

    $this->lockKey = function (): string {
        return 'locker-account-lock:'.$this->accountId;
    };

    $this->cooldownKey = function (): string {
        return 'locker-account-cooldown:'.$this->accountId;
    };
});

afterEach(function () {
    foreach ($this->rateLimiterKeys as $key) {
        RateLimiter::clear($key);
    }
});

test('challenge is allowed when ip is not locked', function () {
    Locker::factory()->create(['account_id' => $this->accountId]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->getJson(route('lockers.challenge', $this->accountId));

    $response->assertStatus(200)->assertJsonStructure(['challenge']);
});

test('challenge is blocked when ip is locked', function () {
    ($this->trackKey)(($this->ipKey)());

    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->getJson(route('lockers.challenge', $this->accountId));

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
});

test('challenge ip lock expires and allows access', function () {
    ($this->trackKey)(($this->ipKey)());

    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);

    // Simulate expiry
    RateLimiter::clear(($this->ipKey)());

    Locker::factory()->create(['account_id' => $this->accountId]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->getJson(route('lockers.challenge', $this->accountId));

    $response->assertStatus(200);
});

test('unlock is blocked when ip is locked', function () {
    ($this->trackKey)(($this->ipKey)());

    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), [
            'verifier' => str_repeat('a', 64),
        ]);

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
});

test('ip is locked after 3 failed unlocks on nonexistent account', function () {
    ($this->trackKey)(($this->ipKey)());

    $badAccount = str_pad((string) ($this->lockerSeq * 100), 10, '9');

    for ($i = 0; $i < 3; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $badAccount), [
                'verifier' => str_repeat('a', 64),
            ])->assertStatus(401);
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $badAccount), [
            'verifier' => str_repeat('a', 64),
        ]);

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
});

test('ip lock expires and allows unlock', function () {
    ($this->trackKey)(($this->ipKey)());

    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);
    RateLimiter::hit(($this->ipKey)(), 3600);

    // Simulate expiry
    RateLimiter::clear(($this->ipKey)());

    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), [
            'verifier' => str_repeat('a', 64),
        ]);

    // IP no longer blocked — gets 401 (wrong verifier/account)
    $response->assertStatus(401);
});

test('ip lock is not triggered by wrong verifier on existing account', function () {
    ($this->trackKey)(($this->ipKey)());
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    // 3 wrong verifier attempts against an existing locker
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::clear(($this->cooldownKey)()); // bypass cooldown between attempts
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), [
                'verifier' => str_repeat('b', 64),
            ])->assertStatus(401);
    }

    // IP should NOT be locked
    expect(RateLimiter::tooManyAttempts(($this->ipKey)(), 3))->toBeFalse();
});

test('account cooldown triggers after wrong verifier', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    // First wrong verifier sets cooldown
    $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), [
            'verifier' => str_repeat('b', 64),
        ])->assertStatus(401);

    // Immediate retry blocked by cooldown
    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), [
            'verifier' => str_repeat('b', 64),
        ]);

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many attempts. Please wait 5 minutes before trying again.']);
});

test('account cooldown resets on each failure', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    // First failure
    $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
        ->assertStatus(401);

    // Simulate cooldown expiry then second failure (which should reset the cooldown)
    RateLimiter::clear(($this->cooldownKey)());

    $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
        ->assertStatus(401);

    // Cooldown is active again after the 2nd failure
    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many attempts. Please wait 5 minutes before trying again.']);
});

test('account cooldown expires and allows retry', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
        ->assertStatus(401);

    // Simulate cooldown expiry
    RateLimiter::clear(($this->cooldownKey)());

    // Should now get through the cooldown gate (401 for wrong verifier, not 429)
    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

    $response->assertStatus(401);
});

test('account is hard locked after 3 wrong verifiers', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    for ($i = 0; $i < 3; $i++) {
        RateLimiter::clear(($this->cooldownKey)()); // bypass cooldown between attempts
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);
    }

    RateLimiter::clear(($this->cooldownKey)());

    // bypass cooldown for the 4th attempt
    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

    $response->assertStatus(429)
        ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
});

test('account hard lock clears cooldown on trigger', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    for ($i = 0; $i < 3; $i++) {
        RateLimiter::clear(($this->cooldownKey)());
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);
    }

    // After 3 failures, cooldown should be cleared (lock takes over)
    expect(RateLimiter::tooManyAttempts(($this->cooldownKey)(), 1))->toBeFalse();
    expect(RateLimiter::tooManyAttempts(($this->lockKey)(), 3))->toBeTrue();
});

test('account hard lock expires and allows retry', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    for ($i = 0; $i < 3; $i++) {
        RateLimiter::clear(($this->cooldownKey)());
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);
    }

    // Simulate lock expiry
    RateLimiter::clear(($this->lockKey)());

    // Assert precondition: lock is gone
    expect(RateLimiter::tooManyAttempts(($this->lockKey)(), 3))->toBeFalse();

    // Should get through — 401 for wrong verifier, not 429
    $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

    $response->assertStatus(401);
});

test('success clears account rate limit counters', function () {
    ($this->trackKey)(($this->lockKey)());
    ($this->trackKey)(($this->cooldownKey)());
    ($this->trackKey)(($this->ipKey)());

    Locker::factory()->create([
        'account_id' => $this->accountId,
        'auth_verifier' => str_repeat('a', 64),
    ]);

    // 2 failures
    for ($i = 0; $i < 2; $i++) {
        RateLimiter::clear(($this->cooldownKey)());
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);
    }

    // Correct verifier — success
    RateLimiter::clear(($this->cooldownKey)());
    $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
        ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('a', 64)])
        ->assertStatus(200);

    // After success, counters are cleared — user can fail again from zero
    expect(RateLimiter::tooManyAttempts(($this->lockKey)(), 3))->toBeFalse();
    expect(RateLimiter::tooManyAttempts(($this->cooldownKey)(), 1))->toBeFalse();
});
