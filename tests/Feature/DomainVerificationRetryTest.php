<?php

namespace Tests\Feature;

use App\Exceptions\DnsVerificationPendingException;
use App\Jobs\RetryDomainVerification;
use App\Models\Plan;
use App\Models\SenderIdentity;
use App\Models\User;
use App\Notifications\DomainVerificationTimeoutNotification;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainVerificationRetryTest extends TestCase
{
    use RefreshDatabase;

    private function createPrimeUser(): User
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_retry',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    private function makeDomainIdentity(User $user, array $attrs = []): SenderIdentity
    {
        return SenderIdentity::factory()->for($user)->create(array_merge([
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'acme.com',
            'verification_token' => 'flashview-verification-test-abc',
            'verified_at' => null,
            'verification_retry_dispatched_at' => null,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // Controller dispatch
    // -----------------------------------------------------------------------

    public function test_retry_job_dispatched_on_first_failed_verify(): void
    {
        Queue::fake();

        $user = $this->createPrimeUser();
        $this->makeDomainIdentity($user);

        $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        $this->actingAs($user)->post(route('user.sender-identity.verify'));

        Queue::assertPushed(RetryDomainVerification::class);
        $this->assertNotNull($user->fresh()->senderIdentity->verification_retry_dispatched_at);
    }

    public function test_retry_job_not_dispatched_when_retry_already_active(): void
    {
        Queue::fake();

        $user = $this->createPrimeUser();
        $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        $this->actingAs($user)
            ->post(route('user.sender-identity.verify'))
            ->assertSessionHasErrors(['domain' => "We're already working on verifying your domain in the background. You'll receive an email once it's done."]);

        Queue::assertNotPushed(RetryDomainVerification::class);
    }

    public function test_retry_flag_cleared_on_manual_verify_success(): void
    {
        Queue::fake();
        Notification::fake();

        $user = $this->createPrimeUser();
        $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $this->actingAs($user)->post(route('user.sender-identity.verify'));

        $this->assertNull($user->fresh()->senderIdentity->verification_retry_dispatched_at);
    }

    public function test_domain_change_clears_retry_flag(): void
    {
        $user = $this->createPrimeUser();
        $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $this->actingAs($user)->post(route('user.sender-identity.store'), [
            'type' => 'domain',
            'company_name' => 'Acme Corp',
            'domain' => 'new-acme.com',
        ]);

        $this->assertNull($user->fresh()->senderIdentity->verification_retry_dispatched_at);
    }

    public function test_switching_to_email_type_clears_retry_flag(): void
    {
        $user = $this->createPrimeUser();
        $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $this->actingAs($user)->post(route('user.sender-identity.store'), ['type' => 'email']);

        $this->assertNull($user->fresh()->senderIdentity->verification_retry_dispatched_at);
    }

    // -----------------------------------------------------------------------
    // Job handle()
    // -----------------------------------------------------------------------

    public function test_job_verifies_and_clears_flag_on_success(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, [
            'verification_retry_dispatched_at' => now(),
        ]);

        $service = $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
        $job->handle($service);

        $identity->refresh();
        $this->assertNotNull($identity->verified_at);
        $this->assertNull($identity->verification_retry_dispatched_at);
        Notification::assertSentTo($user, DomainVerifiedNotification::class);
    }

    public function test_job_throws_on_dns_not_found_to_trigger_retry(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user);

        $service = $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        $this->expectException(DnsVerificationPendingException::class);

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
        $job->handle($service);
    }

    public function test_job_stops_silently_on_token_mismatch(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, [
            'verification_token' => 'new-token-after-domain-change',
            'verification_retry_dispatched_at' => now(),
        ]);

        $service = $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->never();
        });

        $job = new RetryDomainVerification($identity, 'old-token-at-dispatch', now()->addHours(24));
        $job->handle($service);

        Notification::assertNothingSent();
    }

    public function test_job_stops_silently_when_identity_deleted(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user);
        $token = $identity->verification_token;
        $identity->delete();

        $service = $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->never();
        });

        $job = new RetryDomainVerification($identity, $token, now()->addHours(24));
        $job->handle($service);

        Notification::assertNothingSent();
    }

    public function test_job_stops_silently_when_already_verified(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, [
            'verified_at' => now(),
            'verification_retry_dispatched_at' => now(),
        ]);

        $service = $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->never();
        });

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
        $job->handle($service);

        $this->assertNull($identity->fresh()->verification_retry_dispatched_at);
        Notification::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // Job failed()
    // -----------------------------------------------------------------------

    public function test_failed_callback_clears_flag_and_sends_timeout_notification(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
        $job->failed(new \RuntimeException('timeout'));

        $this->assertNull($identity->fresh()->verification_retry_dispatched_at);
        Notification::assertSentTo($user, DomainVerificationTimeoutNotification::class);
    }

    public function test_failed_callback_does_nothing_if_token_changed(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, [
            'verification_token' => 'new-token',
        ]);

        $job = new RetryDomainVerification($identity, 'old-token-at-dispatch', now()->addHours(24));
        $job->failed(new \RuntimeException('timeout'));

        Notification::assertNothingSent();
    }

    public function test_failed_callback_does_nothing_if_identity_deleted(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user);
        $token = $identity->verification_token;
        $identity->delete();

        $job = new RetryDomainVerification($identity, $token, now()->addHours(24));
        $job->failed(new \RuntimeException('timeout'));

        Notification::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // Job configuration
    // -----------------------------------------------------------------------

    public function test_job_has_correct_backoff_schedule(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user);

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));

        $this->assertEquals([120, 240, 480, 960, 1920, 3840, 7680, 15360, 30720], $job->backoff());
    }

    public function test_job_retry_until_returns_provided_deadline(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user);
        $deadline = now()->addHours(24);

        $job = new RetryDomainVerification($identity, $identity->verification_token, $deadline);

        $this->assertEquals($deadline, $job->retryUntil());

        Carbon::setTestNow();
    }
}
