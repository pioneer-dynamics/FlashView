<?php

use App\Models\SecureLineProduct;
use Inertia\Testing\AssertableInertia;

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Secure Line',
        'duration_minutes' => 30,
        'max_participants' => 5,
        'amount_cents' => 2000,
        'stripe_price_id' => '',
        'create_stripe_price' => false,
        'is_active' => true,
    ], $overrides);
}

test('unauthenticated user is redirected from admin secure line products', function () {
    $response = $this->get(route('admin.secure-line-products.index'));

    $response->assertRedirect('/login');
});

test('non admin receives 403 on admin secure line products', function () {
    $user = nonAdminUser();

    $response = $this->actingAs($user)->get(route('admin.secure-line-products.index'));

    $response->assertStatus(403);
});

test('admin can view secure line products index', function () {
    $admin = adminUser();
    SecureLineProduct::factory()->create(['name' => 'Quick Call']);

    $response = $this->actingAs($admin)->get(route('admin.secure-line-products.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Admin/SecureLineProducts/Index')
        ->has('products', 1)
    );
});

test('admin can create product with mapped stripe price id', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        productPayload([
            'name' => 'Premium Call',
            'stripe_price_id' => 'price_mapped123',
        ])
    );

    $response->assertRedirect(route('admin.secure-line-products.index'));
    $this->assertDatabaseHas('secure_line_products', [
        'name' => 'Premium Call',
        'stripe_price_id' => 'price_mapped123',
    ]);
});

test('admin can create product without stripe price id', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        productPayload(['stripe_price_id' => null])
    );

    $response->assertRedirect(route('admin.secure-line-products.index'));
    $this->assertDatabaseHas('secure_line_products', [
        'name' => 'Test Secure Line',
        'stripe_price_id' => null,
    ]);
});

test('admin can view edit form for product', function () {
    $admin = adminUser();
    $product = SecureLineProduct::factory()->withStripePrice()->create();

    $response = $this->actingAs($admin)->get(route('admin.secure-line-products.edit', $product));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Admin/SecureLineProducts/Form')
        ->where('product.id', $product->id)
    );
});

test('admin can update a product', function () {
    $admin = adminUser();
    $product = SecureLineProduct::factory()->withStripePrice()->create();

    $response = $this->actingAs($admin)->putJson(
        route('admin.secure-line-products.update', $product),
        productPayload([
            'name' => 'Updated Name',
            'duration_minutes' => 60,
            'stripe_price_id' => $product->stripe_price_id,
        ])
    );

    $response->assertRedirect(route('admin.secure-line-products.index'));
    $product->refresh();
    expect($product->name)->toEqual('Updated Name');
    expect($product->duration_minutes)->toEqual(60);
});

test('admin can deactivate a product', function () {
    $admin = adminUser();
    $product = SecureLineProduct::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->putJson(
        route('admin.secure-line-products.update', $product),
        productPayload(['is_active' => false])
    );

    $product->refresh();
    expect($product->is_active)->toBeFalse();
});

test('admin can delete a product', function () {
    $admin = adminUser();
    $product = SecureLineProduct::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.secure-line-products.destroy', $product));

    $response->assertRedirect(route('admin.secure-line-products.index'));
    $this->assertSoftDeleted('secure_line_products', ['id' => $product->id]);
});

test('store validates required fields', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        []
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'duration_minutes', 'max_participants', 'amount_cents']);
});

test('duration minutes must be at least 1', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        productPayload(['duration_minutes' => 0])
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['duration_minutes']);
});

test('max participants must be at least 2', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(
        route('admin.secure-line-products.store'),
        productPayload(['max_participants' => 1])
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['max_participants']);
});

test('non admin cannot create product', function () {
    $user = nonAdminUser();

    $response = $this->actingAs($user)->postJson(
        route('admin.secure-line-products.store'),
        productPayload()
    );

    $response->assertStatus(403);
});

test('non admin cannot update product', function () {
    $user = nonAdminUser();
    $product = SecureLineProduct::factory()->create();

    $response = $this->actingAs($user)->putJson(
        route('admin.secure-line-products.update', $product),
        productPayload()
    );

    $response->assertStatus(403);
});

test('non admin cannot delete product', function () {
    $user = nonAdminUser();
    $product = SecureLineProduct::factory()->create();

    $response = $this->actingAs($user)->delete(route('admin.secure-line-products.destroy', $product));

    $response->assertStatus(403);
});
