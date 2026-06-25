<?php

use App\Models\Plan;
use App\Services\StripePlanService;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function planStripeBasePayload(array $overrides = []): array
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
            'messages' => ['order' => 1,   'type' => 'limit',   'config' => ['message_length' => 5000]],
            'expiry' => ['order' => 3,   'type' => 'limit',   'config' => ['expiry_minutes' => 43200]],
            'throttling' => ['order' => 4,   'type' => 'feature', 'config' => []],
            'email_notification' => ['order' => 4.5, 'type' => 'feature', 'config' => []],
            'support' => ['order' => 5,   'type' => 'feature', 'config' => []],
            'api' => ['order' => 6,   'type' => 'feature', 'config' => []],
        ],
    ], $overrides);
}

test('admin creating plan calls stripe and stores ids', function () {
    $admin = adminUser();

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

    $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), planStripeBasePayload());

    $response->assertRedirect(route('admin.plans.index'));
    $this->assertDatabaseHas('plans', [
        'name' => 'Pro Plan',
        'stripe_product_id' => 'prod_new123',
        'stripe_monthly_price_id' => 'price_monthly_new',
        'stripe_yearly_price_id' => 'price_yearly_new',
    ]);
});

test('admin updating pricing creates new stripe prices and archives old ones', function () {
    $admin = adminUser();
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

    $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), planStripeBasePayload([
        'name' => 'Pro Plan',
    ]));

    $response->assertRedirect(route('admin.plans.index'));
    $this->assertDatabaseHas('plans', [
        'id' => $plan->id,
        'stripe_product_id' => 'prod_new456',
        'stripe_monthly_price_id' => 'price_monthly_new',
        'stripe_yearly_price_id' => 'price_yearly_new',
    ]);
});

test('stripe partial failure leaves plan unchanged', function () {
    $admin = adminUser();
    $plan = Plan::factory()->create([
        'stripe_product_id' => 'prod_original',
        'stripe_monthly_price_id' => 'price_monthly_original',
        'stripe_yearly_price_id' => 'price_yearly_original',
    ]);

    $stripeService = Mockery::mock(StripePlanService::class);
    $stripeService->shouldReceive('createProductAndPrices')
        ->once()
        ->andThrow(new Exception('Stripe API error'));
    $stripeService->shouldNotReceive('archivePrices');
    $this->app->instance(StripePlanService::class, $stripeService);

    $this->withoutExceptionHandling();
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Stripe API error');

    try {
        $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), planStripeBasePayload());
    } finally {
        $plan->refresh();
        expect($plan->stripe_product_id)->toEqual('prod_original');
        expect($plan->stripe_monthly_price_id)->toEqual('price_monthly_original');
        expect($plan->stripe_yearly_price_id)->toEqual('price_yearly_original');
    }
});
