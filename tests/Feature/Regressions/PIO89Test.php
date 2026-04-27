<?php

namespace Tests\Feature\Regressions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIO-89: Updating a user with stripe_id="" threw
 * Stripe\Exception\InvalidArgumentException because Cashier's hasStripeId()
 * only checks !is_null(), while Stripe's SDK rejects empty strings too.
 *
 * The Stripe SDK validates the resource ID before any HTTP call, so no real
 * Stripe credentials are needed to reproduce the error. The test suite already
 * uses the sync queue driver via phpunit.xml, so no queue configuration is needed.
 */
class PIO89Test extends TestCase
{
    use RefreshDatabase;

    public function test_user_update_with_empty_stripe_id_does_not_throw_stripe_exception(): void
    {
        $this->expectNotToPerformAssertions();

        $user = User::factory()->create(['stripe_id' => '']);

        // Must not throw Stripe\Exception\InvalidArgumentException.
        $user->update(['name' => 'Updated Name']);
    }
}
