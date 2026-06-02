<?php

namespace Tests\Feature\Locker;

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LockerRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private string $ip;

    private string $accountId;

    /** @var array<string> Keys to clear in tearDown */
    private array $rateLimiterKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$seq++;
        $this->ip = '10.1.1.'.self::$seq;
        $this->accountId = str_pad((string) self::$seq, 10, '0', STR_PAD_LEFT);
    }

    protected function tearDown(): void
    {
        foreach ($this->rateLimiterKeys as $key) {
            RateLimiter::clear($key);
        }
        parent::tearDown();
    }

    private function trackKey(string $key): void
    {
        $this->rateLimiterKeys[] = $key;
    }

    private function ipKey(): string
    {
        return 'locker-ip:'.$this->ip;
    }

    private function lockKey(): string
    {
        return 'locker-account-lock:'.$this->accountId;
    }

    private function cooldownKey(): string
    {
        return 'locker-account-cooldown:'.$this->accountId;
    }

    // --- challenge() IP lock ---

    public function test_challenge_is_allowed_when_ip_is_not_locked(): void
    {
        Locker::factory()->create(['account_id' => $this->accountId]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->getJson(route('lockers.challenge', $this->accountId));

        $response->assertStatus(200)->assertJsonStructure(['challenge']);
    }

    public function test_challenge_is_blocked_when_ip_is_locked(): void
    {
        $this->trackKey($this->ipKey());

        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->getJson(route('lockers.challenge', $this->accountId));

        $response->assertStatus(429)
            ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
    }

    public function test_challenge_ip_lock_expires_and_allows_access(): void
    {
        $this->trackKey($this->ipKey());

        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);

        // Simulate expiry
        RateLimiter::clear($this->ipKey());

        Locker::factory()->create(['account_id' => $this->accountId]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->getJson(route('lockers.challenge', $this->accountId));

        $response->assertStatus(200);
    }

    // --- unlock() IP lock ---

    public function test_unlock_is_blocked_when_ip_is_locked(): void
    {
        $this->trackKey($this->ipKey());

        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), [
                'verifier' => str_repeat('a', 64),
            ]);

        $response->assertStatus(429)
            ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
    }

    public function test_ip_is_locked_after_3_failed_unlocks_on_nonexistent_account(): void
    {
        $this->trackKey($this->ipKey());

        $badAccount = str_pad((string) (self::$seq * 100), 10, '9');

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
    }

    public function test_ip_lock_expires_and_allows_unlock(): void
    {
        $this->trackKey($this->ipKey());

        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);
        RateLimiter::hit($this->ipKey(), 3600);

        // Simulate expiry
        RateLimiter::clear($this->ipKey());

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), [
                'verifier' => str_repeat('a', 64),
            ]);

        // IP no longer blocked — gets 401 (wrong verifier/account)
        $response->assertStatus(401);
    }

    public function test_ip_lock_is_not_triggered_by_wrong_verifier_on_existing_account(): void
    {
        $this->trackKey($this->ipKey());
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        // 3 wrong verifier attempts against an existing locker
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear($this->cooldownKey()); // bypass cooldown between attempts
            $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
                ->postJson(route('lockers.unlock', $this->accountId), [
                    'verifier' => str_repeat('b', 64),
                ])->assertStatus(401);
        }

        // IP should NOT be locked
        $this->assertFalse(RateLimiter::tooManyAttempts($this->ipKey(), 3));
    }

    // --- unlock() account cooldown ---

    public function test_account_cooldown_triggers_after_wrong_verifier(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

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
    }

    public function test_account_cooldown_resets_on_each_failure(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        // First failure
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);

        // Simulate cooldown expiry then second failure (which should reset the cooldown)
        RateLimiter::clear($this->cooldownKey());

        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);

        // Cooldown is active again after the 2nd failure
        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

        $response->assertStatus(429)
            ->assertJson(['error' => 'Too many attempts. Please wait 5 minutes before trying again.']);
    }

    public function test_account_cooldown_expires_and_allows_retry(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
            ->assertStatus(401);

        // Simulate cooldown expiry
        RateLimiter::clear($this->cooldownKey());

        // Should now get through the cooldown gate (401 for wrong verifier, not 429)
        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

        $response->assertStatus(401);
    }

    // --- unlock() account hard lock ---

    public function test_account_is_hard_locked_after_3_wrong_verifiers(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear($this->cooldownKey()); // bypass cooldown between attempts
            $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
                ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
                ->assertStatus(401);
        }

        RateLimiter::clear($this->cooldownKey()); // bypass cooldown for the 4th attempt

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

        $response->assertStatus(429)
            ->assertJson(['error' => 'Too many failed attempts. Try again in 1 hour.']);
    }

    public function test_account_hard_lock_clears_cooldown_on_trigger(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear($this->cooldownKey());
            $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
                ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
                ->assertStatus(401);
        }

        // After 3 failures, cooldown should be cleared (lock takes over)
        $this->assertFalse(RateLimiter::tooManyAttempts($this->cooldownKey(), 1));
        $this->assertTrue(RateLimiter::tooManyAttempts($this->lockKey(), 3));
    }

    public function test_account_hard_lock_expires_and_allows_retry(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear($this->cooldownKey());
            $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
                ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
                ->assertStatus(401);
        }

        // Simulate lock expiry
        RateLimiter::clear($this->lockKey());

        // Assert precondition: lock is gone
        $this->assertFalse(RateLimiter::tooManyAttempts($this->lockKey(), 3));

        // Should get through — 401 for wrong verifier, not 429
        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)]);

        $response->assertStatus(401);
    }

    // --- Success clears counters ---

    public function test_success_clears_account_rate_limit_counters(): void
    {
        $this->trackKey($this->lockKey());
        $this->trackKey($this->cooldownKey());
        $this->trackKey($this->ipKey());

        Locker::factory()->create([
            'account_id' => $this->accountId,
            'auth_verifier' => str_repeat('a', 64),
        ]);

        // 2 failures
        for ($i = 0; $i < 2; $i++) {
            RateLimiter::clear($this->cooldownKey());
            $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
                ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('b', 64)])
                ->assertStatus(401);
        }

        // Correct verifier — success
        RateLimiter::clear($this->cooldownKey());
        $this->withServerVariables(['REMOTE_ADDR' => $this->ip])
            ->postJson(route('lockers.unlock', $this->accountId), ['verifier' => str_repeat('a', 64)])
            ->assertStatus(200);

        // After success, counters are cleared — user can fail again from zero
        $this->assertFalse(RateLimiter::tooManyAttempts($this->lockKey(), 3));
        $this->assertFalse(RateLimiter::tooManyAttempts($this->cooldownKey(), 1));
    }
}
