<?php

namespace App\Services;

use Laravel\Cashier\Cashier;
use Stripe\Coupon;
use Stripe\PromotionCode;

class StripePromotionService
{
    public function listCoupons(): array
    {
        return Cashier::stripe()->coupons->all(['limit' => 100])->data;
    }

    public function createCoupon(array $data): Coupon
    {
        return Cashier::stripe()->coupons->create($data);
    }

    public function retrieveCoupon(string $id): Coupon
    {
        return Cashier::stripe()->coupons->retrieve($id);
    }

    public function deleteCoupon(string $id): void
    {
        Cashier::stripe()->coupons->delete($id);
    }

    public function getPromotionCodesForCoupon(string $couponId): array
    {
        return Cashier::stripe()->promotionCodes->all([
            'coupon' => $couponId,
            'limit' => 100,
        ])->data;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function createPromotionCode(string $couponId, string $code, array $extra = []): PromotionCode
    {
        return Cashier::stripe()->promotionCodes->create(array_merge([
            'coupon' => $couponId,
            'code' => $code,
        ], $extra));
    }

    public function updatePromotionCode(string $id, bool $active): void
    {
        Cashier::stripe()->promotionCodes->update($id, ['active' => $active]);
    }
}
