<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\FeatureRegistry;
use App\Services\StripePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\SubscriptionItem;

class AdminPlanController extends Controller
{
    public function __construct(private readonly StripePlanService $stripeService) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::get()->sortBy('price_per_month')->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => null,
            'defaultStripeMode' => app()->environment('production') ? 'create' : 'map',
            'availableFeatures' => app(FeatureRegistry::class)->forFrontend(),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        if ($request->boolean('create_stripe_product')) {
            $stripeIds = $this->stripeService->createProductAndPrices(
                $request->name,
                (int) round($request->price_per_month * 100),
                (int) round($request->price_per_year * 100),
            );
        } else {
            $this->validateStripeAmounts($request);
            $stripeIds = [
                'product_id' => $request->stripe_product_id ?? '',
                'monthly_price_id' => $request->stripe_monthly_price_id ?? '',
                'yearly_price_id' => $request->stripe_yearly_price_id ?? '',
            ];
        }

        Plan::create([
            'name' => $request->name,
            'price_per_month' => $request->price_per_month,
            'price_per_year' => $request->price_per_year,
            'stripe_product_id' => $stripeIds['product_id'],
            'stripe_monthly_price_id' => $stripeIds['monthly_price_id'],
            'stripe_yearly_price_id' => $stripeIds['yearly_price_id'],
            'features' => $request->features,
        ]);

        return redirect()->route('admin.plans.index')->with('flash', ['success' => 'Plan created.']);
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => $plan,
            'defaultStripeMode' => app()->environment('production') ? 'create' : 'map',
            'availableFeatures' => app(FeatureRegistry::class)->forFrontend(),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        if ($request->boolean('create_stripe_product')) {
            $oldMonthlyPriceId = $plan->stripe_monthly_price_id;
            $oldYearlyPriceId = $plan->stripe_yearly_price_id;

            // Create all Stripe prices first — if any fail, an exception propagates and DB is never touched
            $stripeIds = $this->stripeService->createProductAndPrices(
                $request->name,
                (int) round($request->price_per_month * 100),
                (int) round($request->price_per_year * 100),
            );

            // Commit everything in a single transaction
            DB::transaction(function () use ($plan, $request, $stripeIds) {
                $plan->update([
                    'name' => $request->name,
                    'price_per_month' => $request->price_per_month,
                    'price_per_year' => $request->price_per_year,
                    'stripe_product_id' => $stripeIds['product_id'],
                    'stripe_monthly_price_id' => $stripeIds['monthly_price_id'],
                    'stripe_yearly_price_id' => $stripeIds['yearly_price_id'],
                    'features' => $request->features,
                ]);
            });

            // Archive old prices only after the DB commit succeeds
            $this->stripeService->archivePrices($oldMonthlyPriceId, $oldYearlyPriceId);
        } else {
            $this->validateStripeAmounts($request);

            $plan->update([
                'name' => $request->name,
                'price_per_month' => $request->price_per_month,
                'price_per_year' => $request->price_per_year,
                'stripe_product_id' => $request->stripe_product_id ?? $plan->stripe_product_id ?? '',
                'stripe_monthly_price_id' => $request->stripe_monthly_price_id ?? $plan->stripe_monthly_price_id ?? '',
                'stripe_yearly_price_id' => $request->stripe_yearly_price_id ?? $plan->stripe_yearly_price_id ?? '',
                'features' => $request->features,
            ]);
        }

        return redirect()->route('admin.plans.index')->with('flash', ['success' => 'Plan updated.']);
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $priceIds = array_filter([$plan->stripe_monthly_price_id, $plan->stripe_yearly_price_id]);

        if (! empty($priceIds) && SubscriptionItem::whereIn('stripe_price', $priceIds)->exists()) {
            abort(422, 'This plan has active subscribers and cannot be deleted.');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('flash', ['success' => 'Plan deleted.']);
    }

    private function validateStripeAmounts(StorePlanRequest|UpdatePlanRequest $request): void
    {
        if (filled($request->stripe_monthly_price_id)) {
            $actualMonthly = $this->stripeService->fetchPriceAmountCents($request->stripe_monthly_price_id);
            $enteredMonthly = (int) round($request->price_per_month * 100);

            if (abs($actualMonthly - $enteredMonthly) > 1) {
                abort(422, 'Monthly price mismatch: entered A$'.number_format($request->price_per_month, 2).' but Stripe has A$'.number_format($actualMonthly / 100, 2)." for {$request->stripe_monthly_price_id}.");
            }
        }

        if (filled($request->stripe_yearly_price_id)) {
            $actualYearly = $this->stripeService->fetchPriceAmountCents($request->stripe_yearly_price_id);
            $enteredYearly = (int) round($request->price_per_year * 100);

            if (abs($actualYearly - $enteredYearly) > 1) {
                abort(422, 'Yearly price mismatch: entered A$'.number_format($request->price_per_year, 2).' but Stripe has A$'.number_format($actualYearly / 100, 2)." for {$request->stripe_yearly_price_id}.");
            }
        }
    }
}
