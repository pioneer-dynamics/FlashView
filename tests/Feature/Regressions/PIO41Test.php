<?php

namespace Tests\Feature\Regressions;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIO-41: When a CLI token already exists for a device, the authorize page
 * should display the stored device name as read-only text instead of an
 * editable field pre-filled with the CLI-sent hostname.
 */
class PIO41Test extends TestCase
{
    use RefreshDatabase;

    private function createUserWithApiAccess(): User
    {
        $user = User::factory()->withPersonalTeam()->create();

        $plan = Plan::factory()->create([
            'name' => 'Prime',
            'stripe_monthly_price_id' => 'price_monthly_prime',
            'stripe_yearly_price_id' => 'price_yearly_prime',
            'stripe_product_id' => 'prod_prime',
            'price_per_month' => 50,
            'price_per_year' => 500,
            'features' => ['api' => ['order' => 6, 'label' => 'API Access', 'config' => [], 'type' => 'feature']],
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_pio41_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        return $user;
    }

    /**
     * @see https://linear.app/pioneer-dynamics/issue/PIO-41
     *
     * Bug: Re-authorizing an existing CLI device shows an editable name field
     * pre-filled with the CLI-sent hostname instead of the stored device name
     * as read-only text.
     */
    public function test_existing_device_shows_stored_name_as_read_only(): void
    {
        $user = $this->createUserWithApiAccess();

        // Create an existing CLI token with a custom name (simulating first authorization
        // where the user renamed "MyMachine.local" to "MyMachine")
        $token = $user->createToken('MyMachine', ['secrets:create']);
        $token->accessToken->update(['type' => 'cli']);
        $tokenId = $token->accessToken->id;

        // Second login: CLI sends hostname again, but also sends the stored token ID
        $response = $this->actingAs($user)
            ->get("/cli/authorize?port=12345&state=abcdef1234567890&name=MyMachine.local&token_id={$tokenId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Cli/Authorize')
            // The stored name "MyMachine" should be returned, not the CLI-sent "MyMachine.local"
            ->where('existingDeviceName', 'MyMachine')
        );
    }
}
