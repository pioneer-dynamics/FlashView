<?php

use App\Models\Plan;
use App\Services\StripePromotionService;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Stripe\Coupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\PromotionCode;

/**
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Coupon',
        'discount_type' => 'percent',
        'discount_value' => 20,
        'duration' => 'once',
        'applies_to' => null,
        'promo_code' => 'TEST20',
        'max_redemptions' => null,
        'max_redemptions_per_user' => null,
        'minimum_amount' => null,
        'expires_at' => null,
    ], $overrides);
}

function mockPromoService(): MockInterface
{
    $mock = Mockery::mock(StripePromotionService::class);
    app()->instance(StripePromotionService::class, $mock);

    return $mock;
}

function fakeCoupon(array $attrs = []): Coupon
{
    /** @var Coupon $coupon */
    $coupon = Coupon::constructFrom(array_merge([
        'id' => 'coupon_test123',
        'name' => 'Test Coupon',
        'percent_off' => 20.0,
        'amount_off' => null,
        'currency' => null,
        'duration' => 'once',
        'duration_in_months' => null,
        'times_redeemed' => 0,
        'max_redemptions' => null,
        'redeem_by' => null,
        'valid' => true,
        'applies_to' => null,
    ], $attrs));

    return $coupon;
}

function fakePromoCode(array $attrs = []): PromotionCode
{
    /** @var PromotionCode $code */
    $code = PromotionCode::constructFrom(array_merge([
        'id' => 'promo_test456',
        'code' => 'TEST20',
        'active' => true,
        'times_redeemed' => 0,
        'max_redemptions' => null,
        'restrictions' => ['minimum_amount' => null],
        'created' => time(),
    ], $attrs));

    return $code;
}

test('unauthenticated user is redirected from admin coupons', function () {
    $this->get(route('admin.coupons.index'))->assertRedirect('/login');
});

test('non admin receives 403 on admin coupons', function () {
    $user = nonAdminUser();

    $this->actingAs($user)->get(route('admin.coupons.index'))->assertStatus(403);
});

test('admin can view coupon index', function () {
    $admin = adminUser();
    $mock = mockPromoService();
    $mock->shouldReceive('listCoupons')->once()->andReturn([fakeCoupon()]);

    $this->actingAs($admin)
        ->get(route('admin.coupons.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Coupons/Index')
            ->has('coupons', 1)
        );
});

test('admin can view create form', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.coupons.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Coupons/Form'));
});

test('admin can create coupon with percent discount', function () {
    $admin = adminUser();
    $mock = mockPromoService();
    $coupon = fakeCoupon();

    $mock->shouldReceive('createCoupon')
        ->once()
        ->withArgs(fn ($data) => $data['percent_off'] === 20.0 && $data['duration'] === 'once')
        ->andReturn($coupon);

    $mock->shouldReceive('createPromotionCode')
        ->once()
        ->withArgs(fn ($couponId, $code) => $couponId === 'coupon_test123' && $code === 'TEST20')
        ->andReturn(fakePromoCode());

    $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload());

    $response->assertRedirect(route('admin.coupons.show', 'coupon_test123'));
});

test('admin can create coupon with repeating duration', function () {
    $admin = adminUser();
    $mock = mockPromoService();
    $coupon = fakeCoupon(['duration' => 'repeating', 'duration_in_months' => 3]);

    $mock->shouldReceive('createCoupon')
        ->once()
        ->withArgs(fn ($data) => $data['duration'] === 'repeating' && $data['duration_in_months'] === 3)
        ->andReturn($coupon);

    $mock->shouldReceive('createPromotionCode')->once()->andReturn(fakePromoCode());

    $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload([
        'duration' => 'repeating',
        'duration_in_months' => 3,
    ]))->assertRedirect();
});

test('admin can create coupon with minimum amount', function () {
    $admin = adminUser();
    $mock = mockPromoService();

    $mock->shouldReceive('createCoupon')->once()->andReturn(fakeCoupon());

    $mock->shouldReceive('createPromotionCode')
        ->once()
        ->withArgs(function ($couponId, $code, $extra) {
            return isset($extra['restrictions']['minimum_amount'])
                && $extra['restrictions']['minimum_amount'] === 5000; // $50.00 in cents
        })
        ->andReturn(fakePromoCode());

    $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload([
        'minimum_amount' => 50.00,
    ]))->assertRedirect();
});

test('admin can view coupon show page', function () {
    $admin = adminUser();
    $mock = mockPromoService();
    $coupon = fakeCoupon();
    $promoCode = fakePromoCode();

    $mock->shouldReceive('retrieveCoupon')->with('coupon_test123')->once()->andReturn($coupon);
    $mock->shouldReceive('getPromotionCodesForCoupon')->with('coupon_test123')->once()->andReturn([$promoCode]);

    $this->actingAs($admin)
        ->get(route('admin.coupons.show', 'coupon_test123'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Coupons/Show')
            ->has('coupon')
            ->has('promoCodes', 1)
        );
});

test('admin can delete coupon', function () {
    $admin = adminUser();
    $mock = mockPromoService();

    $mock->shouldReceive('deleteCoupon')->with('coupon_test123')->once();

    $this->actingAs($admin)
        ->delete(route('admin.coupons.destroy', 'coupon_test123'))
        ->assertRedirect(route('admin.coupons.index'));
});

test('admin can toggle promo code to inactive', function () {
    $admin = adminUser();
    $mock = mockPromoService();

    $mock->shouldReceive('updatePromotionCode')
        ->once()
        ->withArgs(fn ($id, $active) => $id === 'promo_test456' && $active === false);

    $this->actingAs($admin)
        ->patch(route('admin.coupons.promo-codes.toggle', ['coupon' => 'coupon_test123', 'promoCode' => 'promo_test456']), [
            'active' => false,
        ])
        ->assertRedirect(route('admin.coupons.show', 'coupon_test123'));
});

test('admin can toggle promo code to active', function () {
    $admin = adminUser();
    $mock = mockPromoService();

    $mock->shouldReceive('updatePromotionCode')
        ->once()
        ->withArgs(fn ($id, $active) => $id === 'promo_test456' && $active === true);

    $this->actingAs($admin)
        ->patch(route('admin.coupons.promo-codes.toggle', ['coupon' => 'coupon_test123', 'promoCode' => 'promo_test456']), [
            'active' => true,
        ])
        ->assertRedirect(route('admin.coupons.show', 'coupon_test123'));
});

test('admin can create coupon applies to subscription', function () {
    $admin = adminUser();
    $mock = mockPromoService();
    $plan = Plan::factory()->create(['stripe_product_id' => 'prod_sub_test']);
    $capturedCouponData = null;

    $mock->shouldReceive('createCoupon')
        ->once()
        ->andReturnUsing(function (array $data) use (&$capturedCouponData) {
            $capturedCouponData = $data;

            return fakeCoupon();
        });

    $mock->shouldReceive('createPromotionCode')->once()->andReturn(fakePromoCode());

    $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload([
        'applies_to' => 'subscription',
    ]))->assertRedirect();

    expect($capturedCouponData)->not->toBeNull();
    expect($capturedCouponData['applies_to']['products'])->toContain('prod_sub_test');
});

test('store validates missing required fields', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'discount_type', 'discount_value', 'duration', 'promo_code']);
});

test('store rejects invalid discount type', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload([
        'discount_type' => 'invalid',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['discount_type']);
});

test('store rejects percent discount over 100', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload([
        'discount_type' => 'percent',
        'discount_value' => 110,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['discount_value']);
});

test('store rejects repeating without duration in months', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload([
        'duration' => 'repeating',
        'duration_in_months' => null,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['duration_in_months']);
});

test('store rollback deletes coupon if promo code creation fails', function () {
    $admin = adminUser();
    $mock = mockPromoService();
    $coupon = fakeCoupon();

    $mock->shouldReceive('createCoupon')->once()->andReturn($coupon);
    $mock->shouldReceive('createPromotionCode')->once()->andThrow(new RuntimeException('Stripe error'));
    $mock->shouldReceive('deleteCoupon')->with('coupon_test123')->once();

    $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), couponPayload());

    $response->assertRedirect();
});

test('show redirects to index when coupon not found', function () {
    $admin = adminUser();
    $mock = mockPromoService();

    $exception = InvalidRequestException::factory('No such coupon', 404);
    $mock->shouldReceive('retrieveCoupon')->once()->andThrow($exception);
    $mock->shouldReceive('getPromotionCodesForCoupon')->never();

    $this->actingAs($admin)
        ->get(route('admin.coupons.show', 'coupon_nonexistent'))
        ->assertRedirect(route('admin.coupons.index'));
});

test('destroy redirects to index when coupon not found', function () {
    $admin = adminUser();
    $mock = mockPromoService();

    $exception = InvalidRequestException::factory('No such coupon', 404);
    $mock->shouldReceive('deleteCoupon')->once()->andThrow($exception);

    $this->actingAs($admin)
        ->delete(route('admin.coupons.destroy', 'coupon_nonexistent'))
        ->assertRedirect(route('admin.coupons.index'));
});

test('non admin cannot store coupon', function () {
    $user = nonAdminUser();

    $this->actingAs($user)
        ->postJson(route('admin.coupons.store'), couponPayload())
        ->assertStatus(403);
});

test('non admin cannot toggle promo code', function () {
    $user = nonAdminUser();

    $this->actingAs($user)
        ->patch(route('admin.coupons.promo-codes.toggle', ['coupon' => 'coupon_test123', 'promoCode' => 'promo_test456']), [
            'active' => false,
        ])
        ->assertStatus(403);
});
