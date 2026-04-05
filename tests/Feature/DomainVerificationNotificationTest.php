<?php

namespace Tests\Feature;

use App\Jobs\RetryDomainVerification;
use App\Models\Plan;
use App\Models\SenderIdentity;
use App\Models\User;
use App\Notifications\DomainLapsedNotification;
use App\Notifications\DomainVerificationTimeoutNotification;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DomainVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createPrimeUser(): User
    {
        $plan = Plan::factory()->withSenderIdentity()->create();
        $user = User::factory()->withPersonalTeam()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_notify',
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

    public function test_domain_verified_notification_sent_on_manual_success(): void
    {
        Notification::fake();

        $user = $this->createPrimeUser();
        $this->makeDomainIdentity($user);

        $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $this->actingAs($user)->post(route('user.sender-identity.verify'));

        Notification::assertSentTo($user, DomainVerifiedNotification::class);
    }

    public function test_domain_verified_notification_sent_on_job_success(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $service = $this->mock(DomainVerificationService::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
        $job->handle($service);

        Notification::assertSentTo($user, DomainVerifiedNotification::class);
    }

    public function test_domain_verification_timeout_notification_sent_on_exhaustion(): void
    {
        Notification::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $identity = $this->makeDomainIdentity($user, ['verification_retry_dispatched_at' => now()]);

        $job = new RetryDomainVerification($identity, $identity->verification_token, now()->addHours(24));
        $job->failed(new \RuntimeException('retryUntil exceeded'));

        Notification::assertSentTo($user, DomainVerificationTimeoutNotification::class);
        Notification::assertNotSentTo($user, DomainVerifiedNotification::class);
    }

    public function test_domain_lapsed_notification_sent_by_reverify_command(): void
    {
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
    }

    public function test_no_notification_sent_when_already_verified_guard_triggers(): void
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

        Notification::assertNothingSent();
    }
}
