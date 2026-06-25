<?php

use App\Http\Controllers\Admin\AdminSecureLineProductController;
use App\Models\SecureLineProduct;

function productBasePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Quick Call',
        'duration_minutes' => 30,
        'max_participants' => 5,
        'amount_cents' => 2000,
        'stripe_price_id' => '',
        'create_stripe_price' => true,
        'is_active' => true,
    ], $overrides);
}

function mockControllerWithStripeResult(?string $priceId): void
{
    $mock = Mockery::mock(AdminSecureLineProductController::class)->makePartial();
    $mock->shouldAllowMockingProtectedMethods();
    $mock->shouldReceive('createStripePrice')->andReturn($priceId);
    app()->instance(AdminSecureLineProductController::class, $mock);
}

test('store with create stripe price saves returned price id', function () {
    $admin = adminUser();
    mockControllerWithStripeResult('price_test456');

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        productBasePayload(['name' => 'Quick Call'])
    );

    $response->assertRedirect(route('admin.secure-line-products.index'));
    $this->assertDatabaseHas('secure_line_products', [
        'name' => 'Quick Call',
        'stripe_price_id' => 'price_test456',
    ]);
});

test('store with stripe failure redirects back with error', function () {
    $admin = adminUser();
    mockControllerWithStripeResult(null);

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        productBasePayload()
    );

    $response->assertRedirect();
    $this->assertDatabaseMissing('secure_line_products', ['name' => 'Quick Call']);
});

test('update with create stripe price replaces existing price id', function () {
    $admin = adminUser();
    $product = SecureLineProduct::factory()->withStripePrice()->create([
        'stripe_price_id' => 'price_old789',
    ]);
    mockControllerWithStripeResult('price_new123');

    $this->actingAs($admin)->putJson(
        route('admin.secure-line-products.update', $product),
        productBasePayload(['name' => $product->name])
    );

    $product->refresh();
    expect($product->stripe_price_id)->toEqual('price_new123');
});

test('update without create stripe price retains existing price id', function () {
    $admin = adminUser();
    $product = SecureLineProduct::factory()->withStripePrice()->create([
        'stripe_price_id' => 'price_existing999',
    ]);

    // Omit stripe_price_id from the payload to simulate admin not re-entering it
    $response = $this->actingAs($admin)->putJson(
        route('admin.secure-line-products.update', $product),
        [
            'name' => $product->name,
            'duration_minutes' => $product->duration_minutes,
            'max_participants' => $product->max_participants,
            'amount_cents' => $product->amount_cents,
            'create_stripe_price' => false,
            'is_active' => true,
        ]
    );

    $response->assertRedirect(route('admin.secure-line-products.index'));
    $product->refresh();
    expect($product->stripe_price_id)->toEqual('price_existing999');
});
