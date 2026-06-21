<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\StripePromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Stripe\Coupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\PromotionCode;
use Tests\TestCase;

class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Config::set('admin.emails', [$user->email]);

        return $user;
    }

    private function nonAdminUser(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function couponPayload(array $overrides = []): array
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

    private function mockPromoService(): Mockery\MockInterface
    {
        $mock = Mockery::mock(StripePromotionService::class);
        $this->app->instance(StripePromotionService::class, $mock);

        return $mock;
    }

    private function fakeCoupon(array $attrs = []): Coupon
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

    private function fakePromoCode(array $attrs = []): PromotionCode
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

    public function test_unauthenticated_user_is_redirected_from_admin_coupons(): void
    {
        $this->get(route('admin.coupons.index'))->assertRedirect('/login');
    }

    public function test_non_admin_receives_403_on_admin_coupons(): void
    {
        $user = $this->nonAdminUser();

        $this->actingAs($user)->get(route('admin.coupons.index'))->assertStatus(403);
    }

    public function test_admin_can_view_coupon_index(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();
        $mock->shouldReceive('listCoupons')->once()->andReturn([$this->fakeCoupon()]);

        $this->actingAs($admin)
            ->get(route('admin.coupons.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Coupons/Index')
                ->has('coupons', 1)
            );
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.coupons.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Coupons/Form'));
    }

    public function test_admin_can_create_coupon_with_percent_discount(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();
        $coupon = $this->fakeCoupon();

        $mock->shouldReceive('createCoupon')
            ->once()
            ->withArgs(fn ($data) => $data['percent_off'] === 20.0 && $data['duration'] === 'once')
            ->andReturn($coupon);

        $mock->shouldReceive('createPromotionCode')
            ->once()
            ->withArgs(fn ($couponId, $code) => $couponId === 'coupon_test123' && $code === 'TEST20')
            ->andReturn($this->fakePromoCode());

        $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload());

        $response->assertRedirect(route('admin.coupons.show', 'coupon_test123'));
    }

    public function test_admin_can_create_coupon_with_repeating_duration(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();
        $coupon = $this->fakeCoupon(['duration' => 'repeating', 'duration_in_months' => 3]);

        $mock->shouldReceive('createCoupon')
            ->once()
            ->withArgs(fn ($data) => $data['duration'] === 'repeating' && $data['duration_in_months'] === 3)
            ->andReturn($coupon);

        $mock->shouldReceive('createPromotionCode')->once()->andReturn($this->fakePromoCode());

        $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload([
            'duration' => 'repeating',
            'duration_in_months' => 3,
        ]))->assertRedirect();
    }

    public function test_admin_can_create_coupon_with_minimum_amount(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();

        $mock->shouldReceive('createCoupon')->once()->andReturn($this->fakeCoupon());

        $mock->shouldReceive('createPromotionCode')
            ->once()
            ->withArgs(function ($couponId, $code, $extra) {
                return isset($extra['restrictions']['minimum_amount'])
                    && $extra['restrictions']['minimum_amount'] === 5000; // $50.00 in cents
            })
            ->andReturn($this->fakePromoCode());

        $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload([
            'minimum_amount' => 50.00,
        ]))->assertRedirect();
    }

    public function test_admin_can_view_coupon_show_page(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();
        $coupon = $this->fakeCoupon();
        $promoCode = $this->fakePromoCode();

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
    }

    public function test_admin_can_delete_coupon(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();

        $mock->shouldReceive('deleteCoupon')->with('coupon_test123')->once();

        $this->actingAs($admin)
            ->delete(route('admin.coupons.destroy', 'coupon_test123'))
            ->assertRedirect(route('admin.coupons.index'));
    }

    public function test_admin_can_toggle_promo_code_to_inactive(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();

        $mock->shouldReceive('updatePromotionCode')
            ->once()
            ->withArgs(fn ($id, $active) => $id === 'promo_test456' && $active === false);

        $this->actingAs($admin)
            ->patch(route('admin.coupons.promo-codes.toggle', ['coupon' => 'coupon_test123', 'promoCode' => 'promo_test456']), [
                'active' => false,
            ])
            ->assertRedirect(route('admin.coupons.show', 'coupon_test123'));
    }

    public function test_admin_can_toggle_promo_code_to_active(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();

        $mock->shouldReceive('updatePromotionCode')
            ->once()
            ->withArgs(fn ($id, $active) => $id === 'promo_test456' && $active === true);

        $this->actingAs($admin)
            ->patch(route('admin.coupons.promo-codes.toggle', ['coupon' => 'coupon_test123', 'promoCode' => 'promo_test456']), [
                'active' => true,
            ])
            ->assertRedirect(route('admin.coupons.show', 'coupon_test123'));
    }

    public function test_store_validates_missing_required_fields(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'discount_type', 'discount_value', 'duration', 'promo_code']);
    }

    public function test_store_rejects_invalid_discount_type(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload([
            'discount_type' => 'invalid',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['discount_type']);
    }

    public function test_store_rejects_percent_discount_over_100(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload([
            'discount_type' => 'percent',
            'discount_value' => 110,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['discount_value']);
    }

    public function test_store_rejects_repeating_without_duration_in_months(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload([
            'duration' => 'repeating',
            'duration_in_months' => null,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['duration_in_months']);
    }

    public function test_store_rollback_deletes_coupon_if_promo_code_creation_fails(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();
        $coupon = $this->fakeCoupon();

        $mock->shouldReceive('createCoupon')->once()->andReturn($coupon);
        $mock->shouldReceive('createPromotionCode')->once()->andThrow(new \RuntimeException('Stripe error'));
        $mock->shouldReceive('deleteCoupon')->with('coupon_test123')->once();

        $response = $this->actingAs($admin)->postJson(route('admin.coupons.store'), $this->couponPayload());

        $response->assertRedirect();
    }

    public function test_show_redirects_to_index_when_coupon_not_found(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();

        $exception = InvalidRequestException::factory('No such coupon', 404);
        $mock->shouldReceive('retrieveCoupon')->once()->andThrow($exception);
        $mock->shouldReceive('getPromotionCodesForCoupon')->never();

        $this->actingAs($admin)
            ->get(route('admin.coupons.show', 'coupon_nonexistent'))
            ->assertRedirect(route('admin.coupons.index'));
    }

    public function test_destroy_redirects_to_index_when_coupon_not_found(): void
    {
        $admin = $this->adminUser();
        $mock = $this->mockPromoService();

        $exception = InvalidRequestException::factory('No such coupon', 404);
        $mock->shouldReceive('deleteCoupon')->once()->andThrow($exception);

        $this->actingAs($admin)
            ->delete(route('admin.coupons.destroy', 'coupon_nonexistent'))
            ->assertRedirect(route('admin.coupons.index'));
    }

    public function test_non_admin_cannot_store_coupon(): void
    {
        $user = $this->nonAdminUser();

        $this->actingAs($user)
            ->postJson(route('admin.coupons.store'), $this->couponPayload())
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_toggle_promo_code(): void
    {
        $user = $this->nonAdminUser();

        $this->actingAs($user)
            ->patch(route('admin.coupons.promo-codes.toggle', ['coupon' => 'coupon_test123', 'promoCode' => 'promo_test456']), [
                'active' => false,
            ])
            ->assertStatus(403);
    }
}
