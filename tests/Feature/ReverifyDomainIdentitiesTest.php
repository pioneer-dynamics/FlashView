<?php

namespace Tests\Feature;

use App\Models\SenderIdentity;
use App\Models\User;
use App\Notifications\DomainLapsedNotification;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReverifyDomainIdentitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_identity_older_than_3_months_with_passing_dns_is_refreshed(): void
    {
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
        $this->assertNotNull($identity->verified_at);
        $this->assertTrue($identity->verified_at->isAfter($oldVerifiedAt));
    }

    public function test_verified_identity_older_than_3_months_with_failing_dns_is_nulled(): void
    {
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

        $this->assertNull($identity->fresh()->verified_at);
        Notification::assertSentTo($user, DomainLapsedNotification::class);
    }

    public function test_verified_identity_younger_than_3_months_is_skipped(): void
    {
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
        $this->assertNotNull($identity->verified_at);
    }

    public function test_email_type_identities_are_never_queried(): void
    {
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
    }

    public function test_unverified_domain_identities_are_skipped(): void
    {
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
    }

    public function test_command_outputs_pass_and_fail_counts(): void
    {
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
    }
}
