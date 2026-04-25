<?php

namespace App\Services;

use Laravel\Cashier\Cashier;

class StripePlanService
{
    /**
     * Create a new Stripe product with monthly and yearly prices.
     *
     * @return array{product_id: string, monthly_price_id: string, yearly_price_id: string}
     */
    public function createProductAndPrices(string $name, int $monthlyAmountCents, int $yearlyAmountCents): array
    {
        $product = Cashier::stripe()->products->create(['name' => $name]);

        $monthlyPrice = Cashier::stripe()->prices->create([
            'unit_amount' => $monthlyAmountCents,
            'currency' => 'usd',
            'recurring' => ['interval' => 'month'],
            'product' => $product->id,
        ]);

        $yearlyPrice = Cashier::stripe()->prices->create([
            'unit_amount' => $yearlyAmountCents,
            'currency' => 'usd',
            'recurring' => ['interval' => 'year'],
            'product' => $product->id,
        ]);

        return [
            'product_id' => $product->id,
            'monthly_price_id' => $monthlyPrice->id,
            'yearly_price_id' => $yearlyPrice->id,
        ];
    }

    /**
     * Archive Stripe prices so they are no longer available for new subscriptions.
     * Existing subscriptions on archived prices continue to bill normally.
     */
    public function archivePrices(string ...$priceIds): void
    {
        foreach ($priceIds as $priceId) {
            if (blank($priceId)) {
                continue;
            }

            Cashier::stripe()->prices->update($priceId, ['active' => false]);
        }
    }

    /**
     * Fetch the unit_amount (in cents) for a Stripe price ID.
     */
    public function fetchPriceAmountCents(string $priceId): int
    {
        $price = Cashier::stripe()->prices->retrieve($priceId);

        return (int) $price->unit_amount;
    }
}
