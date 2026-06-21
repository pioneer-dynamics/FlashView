<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\TogglePromoCodeRequest;
use App\Models\LockerPlan;
use App\Models\Plan;
use App\Models\SecureLineProduct;
use App\Services\StripePromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

class AdminCouponController extends Controller
{
    public function __construct(private StripePromotionService $stripePromotion) {}

    public function index(): Response
    {
        $coupons = $this->stripePromotion->listCoupons();

        return Inertia::render('Admin/Coupons/Index', ['coupons' => $coupons]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupons/Form');
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $coupon = null;

        try {
            $couponData = $this->buildCouponData($request);
            $coupon = $this->stripePromotion->createCoupon($couponData);

            $promoData = $this->buildPromoCodeData($request);
            $this->stripePromotion->createPromotionCode($coupon->id, strtoupper($request->input('promo_code')), $promoData);

            return redirect()->route('admin.coupons.show', $coupon->id)
                ->with('flash', ['success' => 'Coupon and promotion code '.$request->input('promo_code').' created.']);
        } catch (\Throwable $e) {
            if ($coupon !== null) {
                try {
                    $this->stripePromotion->deleteCoupon($coupon->id);
                } catch (\Throwable) {
                }
            }

            Log::error('Failed to create coupon/promo code', ['error' => $e->getMessage()]);

            return redirect()->back()->with('flash', ['error' => $e->getMessage() ?: 'Failed to create coupon. Check logs.']);
        }
    }

    public function show(string $id): Response|RedirectResponse
    {
        try {
            $coupon = $this->stripePromotion->retrieveCoupon($id);
            $promoCodes = $this->stripePromotion->getPromotionCodesForCoupon($id);

            return Inertia::render('Admin/Coupons/Show', [
                'coupon' => $coupon,
                'promoCodes' => $promoCodes,
            ]);
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return redirect()->route('admin.coupons.index')
                    ->with('flash', ['error' => 'Coupon not found. It may have been deleted.']);
            }

            throw $e;
        }
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            $this->stripePromotion->deleteCoupon($id);
        } catch (InvalidRequestException $e) {
            if ($e->getHttpStatus() === 404) {
                return redirect()->route('admin.coupons.index')
                    ->with('flash', ['error' => 'Coupon not found. It may have already been deleted.']);
            }

            throw $e;
        }

        return redirect()->route('admin.coupons.index')
            ->with('flash', ['success' => 'Coupon deleted.']);
    }

    public function togglePromoCode(TogglePromoCodeRequest $request, string $couponId, string $promoCodeId): RedirectResponse
    {
        $active = $request->boolean('active');
        $this->stripePromotion->updatePromotionCode($promoCodeId, $active);

        return redirect()->route('admin.coupons.show', $couponId)
            ->with('flash', ['success' => $active ? 'Promotion code activated.' : 'Promotion code deactivated.']);
    }

    private function buildCouponData(StoreCouponRequest $request): array
    {
        $data = [
            'name' => $request->input('name'),
            'duration' => $request->input('duration'),
        ];

        if ($request->input('duration') === 'repeating') {
            $data['duration_in_months'] = (int) $request->input('duration_in_months');
        }

        if ($request->input('discount_type') === 'percent') {
            $data['percent_off'] = (float) $request->input('discount_value');
        } else {
            $data['amount_off'] = (int) round($request->input('discount_value') * 100);
            $data['currency'] = strtolower($request->input('currency'));
        }

        if ($request->filled('max_redemptions')) {
            $data['max_redemptions'] = (int) $request->input('max_redemptions');
        }

        if ($request->filled('expires_at')) {
            $data['redeem_by'] = strtotime($request->input('expires_at'));
        }

        if ($request->filled('applies_to')) {
            $productIds = $this->resolveProductIds($request->input('applies_to'));

            if (empty($productIds)) {
                throw new \RuntimeException('No active Stripe products found for the selected restriction. Add active products first or choose "All Products".');
            }

            $data['applies_to'] = ['products' => $productIds];
        }

        return $data;
    }

    private function buildPromoCodeData(StoreCouponRequest $request): array
    {
        $data = [];

        if ($request->filled('max_redemptions_per_user')) {
            $data['max_redemptions'] = (int) $request->input('max_redemptions_per_user');
        }

        $restrictions = [];

        if ($request->filled('minimum_amount')) {
            $restrictions['minimum_amount'] = (int) round($request->input('minimum_amount') * 100);
            $restrictions['minimum_amount_currency'] = strtolower($request->input('currency', config('cashier.currency', 'aud')));
        }

        if (! empty($restrictions)) {
            $data['restrictions'] = $restrictions;
        }

        return $data;
    }

    private function resolveProductIds(string $appliesTo): array
    {
        $priceIds = [];

        if (in_array($appliesTo, ['locker', 'both'])) {
            $priceIds = array_merge(
                $priceIds,
                LockerPlan::where('is_active', true)->whereNotNull('stripe_price_id')->pluck('stripe_price_id')->toArray()
            );
        }

        if (in_array($appliesTo, ['secure_line', 'both'])) {
            $priceIds = array_merge(
                $priceIds,
                SecureLineProduct::where('is_active', true)->whereNotNull('stripe_price_id')->pluck('stripe_price_id')->toArray()
            );
        }

        $productIds = [];

        foreach ($priceIds as $priceId) {
            $price = Cashier::stripe()->prices->retrieve($priceId);
            $productIds[] = $price->product;
        }

        // Plans store stripe_product_id directly — no extra Stripe API call needed.
        if ($appliesTo === 'subscription') {
            $subscriptionProductIds = Plan::whereNotNull('stripe_product_id')
                ->get()
                ->filter(fn (Plan $plan) => $plan->isCurrentlyAvailable())
                ->pluck('stripe_product_id')
                ->toArray();

            $productIds = array_merge($productIds, $subscriptionProductIds);
        }

        return array_unique($productIds);
    }
}
