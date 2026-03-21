<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class CreateApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_can_be_created(): void
    {
        if (! Features::hasApiFeatures()) {
            $this->markTestSkipped('API support is not enabled.');
        }

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

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
            'stripe_id' => 'sub_test_create',
            'stripe_status' => 'active',
            'stripe_price' => $plan->stripe_monthly_price_id,
            'quantity' => 1,
        ]);

        $this->post('/user/api-tokens', [
            'name' => 'Test Token',
            'permissions' => [
                'secrets:create',
                'secrets:list',
            ],
        ]);

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertEquals('Test Token', $user->fresh()->tokens->first()->name);
        $this->assertTrue($user->fresh()->tokens->first()->can('secrets:create'));
        $this->assertFalse($user->fresh()->tokens->first()->can('secrets:delete'));
    }
}
