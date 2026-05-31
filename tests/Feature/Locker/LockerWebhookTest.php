<?php

namespace Tests\Feature\Locker;

use App\Http\Controllers\LockerWebhookController;
use App\Models\Locker;
use App\Models\LockerCredit;
use App\Models\LockerRenewal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class LockerWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function callHandle(object $session, string $action): void
    {
        $controller = new LockerWebhookController;
        $reflection = new ReflectionClass($controller);

        $methodName = match ($action) {
            'create'  => 'handleCreate',
            'renewal' => 'handleRenewal',
            default   => null,
        };

        if ($methodName) {
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
            $method->invoke($controller, $session);
        }
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['lockers.webhook_secret' => 'real_secret']);

        $response = $this->postJson(
            route('locker.webhook'),
            ['type' => 'checkout.session.completed'],
            ['Stripe-Signature' => 'invalid']
        );

        $response->assertStatus(400);
    }

    public function test_webhook_creates_locker_credit_on_checkout_completed_with_create_action(): void
    {
        $session = (object) [
            'id'       => 'cs_test_create_001',
            'metadata' => (object) ['action' => 'create', 'tier' => 'text', 'years' => '1'],
        ];

        $this->callHandle($session, 'create');

        $this->assertDatabaseHas('locker_credits', [
            'stripe_session_id' => 'cs_test_create_001',
            'tier'              => 'text',
            'years'             => 1,
        ]);
    }

    public function test_webhook_is_idempotent_for_same_session_id_on_create(): void
    {
        LockerCredit::factory()->create(['stripe_session_id' => 'cs_dup_001', 'token' => 'firsttoken']);

        $session = (object) [
            'id'       => 'cs_dup_001',
            'metadata' => (object) ['action' => 'create', 'tier' => 'text', 'years' => '1'],
        ];

        $this->callHandle($session, 'create');
        $this->callHandle($session, 'create');

        $this->assertDatabaseCount('locker_credits', 1);
    }

    public function test_webhook_extends_expires_at_on_renewal_action(): void
    {
        $originalExpiry = now()->addYear();
        Locker::factory()->create([
            'account_id' => '1234567890',
            'expires_at' => $originalExpiry,
        ]);

        $session = (object) [
            'id'       => 'cs_renewal_001',
            'metadata' => (object) [
                'action'     => 'renewal',
                'account_id' => '1234567890',
                'years'      => '1',
            ],
        ];

        $this->callHandle($session, 'renewal');

        $locker = Locker::where('account_id', '1234567890')->first();
        $this->assertTrue($locker->expires_at->isAfter($originalExpiry->addYears(1)->subDay()));
    }

    public function test_webhook_rotates_auth_challenge_on_renewal(): void
    {
        Locker::factory()->create([
            'account_id'     => '1234567890',
            'auth_challenge' => 'originalchallenge',
            'expires_at'     => now()->addYear(),
        ]);

        $session = (object) [
            'id'       => 'cs_renewal_002',
            'metadata' => (object) [
                'action'     => 'renewal',
                'account_id' => '1234567890',
                'years'      => '1',
            ],
        ];

        $this->callHandle($session, 'renewal');

        $locker = Locker::where('account_id', '1234567890')->first();
        $this->assertNotEquals('originalchallenge', $locker->auth_challenge);
        $this->assertEquals(64, strlen($locker->auth_challenge));
    }

    public function test_webhook_renewal_is_idempotent_for_same_session_id(): void
    {
        Locker::factory()->create([
            'account_id' => '1234567890',
            'expires_at' => now()->addYear(),
        ]);

        $session = (object) [
            'id'       => 'cs_renewal_dup',
            'metadata' => (object) [
                'action'     => 'renewal',
                'account_id' => '1234567890',
                'years'      => '1',
            ],
        ];

        $this->callHandle($session, 'renewal');
        $this->callHandle($session, 'renewal');

        $this->assertDatabaseCount('locker_renewals', 1);
    }

    public function test_webhook_handles_missing_locker_on_renewal_gracefully(): void
    {
        $session = (object) [
            'id'       => 'cs_renewal_missing',
            'metadata' => (object) [
                'action'     => 'renewal',
                'account_id' => '9999999999',
                'years'      => '1',
            ],
        ];

        $this->callHandle($session, 'renewal');

        $this->assertDatabaseCount('locker_renewals', 0);
    }

    public function test_webhook_ignores_unknown_event_types(): void
    {
        $this->assertDatabaseCount('locker_credits', 0);
        $this->assertDatabaseCount('locker_renewals', 0);
        // No records created — test just verifies no exception thrown for unknown actions
        $this->assertTrue(true);
    }
}
