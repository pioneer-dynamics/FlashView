<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Services\StripePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AdminPlanStripeTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Config::set('admin.emails', [$user->email]);

        return $user;
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pro Plan',
            'price_per_month' => 30.00,
            'price_per_year' => 300.00,
            'create_stripe_product' => true,
            'stripe_product_id' => '',
            'stripe_monthly_price_id' => '',
            'stripe_yearly_price_id' => '',
            'features' => [
                'untracked' => ['order' => 1,   'type' => 'feature', 'config' => []],
                'messages' => ['order' => 2,   'type' => 'limit',   'config' => ['message_length' => 5000]],
                'expiry' => ['order' => 3,   'type' => 'limit',   'config' => ['expiry_minutes' => 43200, 'expiry_label' => '30 days']],
                'throttling' => ['order' => 4,   'type' => 'feature', 'config' => []],
                'email_notification' => ['order' => 4.5, 'type' => 'feature', 'config' => []],
                'support' => ['order' => 5,   'type' => 'feature', 'config' => []],
                'api' => ['order' => 6,   'type' => 'feature', 'config' => []],
            ],
        ], $overrides);
    }

    public function test_admin_creating_plan_calls_stripe_and_stores_ids(): void
    {
        $admin = $this->adminUser();

        $stripeService = Mockery::mock(StripePlanService::class);
        $stripeService->shouldReceive('createProductAndPrices')
            ->once()
            ->with('Pro Plan', 3000, 30000)
            ->andReturn([
                'product_id' => 'prod_new123',
                'monthly_price_id' => 'price_monthly_new',
                'yearly_price_id' => 'price_yearly_new',
            ]);
        $this->app->instance(StripePlanService::class, $stripeService);

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $this->basePayload());

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseHas('plans', [
            'name' => 'Pro Plan',
            'stripe_product_id' => 'prod_new123',
            'stripe_monthly_price_id' => 'price_monthly_new',
            'stripe_yearly_price_id' => 'price_yearly_new',
        ]);
    }

    public function test_admin_updating_pricing_creates_new_stripe_prices_and_archives_old_ones(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create([
            'stripe_product_id' => 'prod_old',
            'stripe_monthly_price_id' => 'price_monthly_old',
            'stripe_yearly_price_id' => 'price_yearly_old',
            'price_per_month' => 30.00,
            'price_per_year' => 300.00,
        ]);

        $stripeService = Mockery::mock(StripePlanService::class);
        $stripeService->shouldReceive('createProductAndPrices')
            ->once()
            ->with('Pro Plan', 3000, 30000)
            ->andReturn([
                'product_id' => 'prod_new456',
                'monthly_price_id' => 'price_monthly_new',
                'yearly_price_id' => 'price_yearly_new',
            ]);
        $stripeService->shouldReceive('archivePrices')
            ->once()
            ->with('price_monthly_old', 'price_yearly_old');
        $this->app->instance(StripePlanService::class, $stripeService);

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->basePayload([
            'name' => 'Pro Plan',
        ]));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'stripe_product_id' => 'prod_new456',
            'stripe_monthly_price_id' => 'price_monthly_new',
            'stripe_yearly_price_id' => 'price_yearly_new',
        ]);
    }

    public function test_stripe_partial_failure_leaves_plan_unchanged(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create([
            'stripe_product_id' => 'prod_original',
            'stripe_monthly_price_id' => 'price_monthly_original',
            'stripe_yearly_price_id' => 'price_yearly_original',
        ]);

        $stripeService = Mockery::mock(StripePlanService::class);
        $stripeService->shouldReceive('createProductAndPrices')
            ->once()
            ->andThrow(new \Exception('Stripe API error'));
        $stripeService->shouldNotReceive('archivePrices');
        $this->app->instance(StripePlanService::class, $stripeService);

        $this->withoutExceptionHandling();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stripe API error');

        try {
            $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $this->basePayload());
        } finally {
            $plan->refresh();
            $this->assertEquals('prod_original', $plan->stripe_product_id);
            $this->assertEquals('price_monthly_original', $plan->stripe_monthly_price_id);
            $this->assertEquals('price_yearly_original', $plan->stripe_yearly_price_id);
        }
    }
}
