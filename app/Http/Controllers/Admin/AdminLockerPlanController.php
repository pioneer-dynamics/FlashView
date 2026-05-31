<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLockerPlanRequest;
use App\Http\Requests\UpdateLockerPlanRequest;
use App\Models\LockerPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;

class AdminLockerPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/LockerPlans/Index', [
            'plans' => LockerPlan::orderBy('tier')->orderBy('years')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/LockerPlans/Form', [
            'plan' => null,
            'defaultStripeMode' => app()->environment('production') ? 'create' : 'map',
        ]);
    }

    public function store(StoreLockerPlanRequest $request): RedirectResponse
    {
        $priceId = $request->input('stripe_price_id');

        if ($request->boolean('create_stripe_price')) {
            $priceId = $this->createStripePrice($request);
            if (! $priceId) {
                return redirect()->back()->with('flash', ['error' => 'Failed to create Stripe price. Check logs.']);
            }
        }

        LockerPlan::create([
            'tier' => $request->input('tier'),
            'years' => (int) $request->input('years'),
            'file_size_mb' => $request->input('tier') === 'file' ? (int) $request->input('file_size_mb') : null,
            'amount_cents' => (int) $request->input('amount_cents'),
            'stripe_price_id' => $priceId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.locker-plans.index')->with('flash', ['success' => 'Locker plan created.']);
    }

    public function edit(LockerPlan $lockerPlan): Response
    {
        return Inertia::render('Admin/LockerPlans/Form', [
            'plan' => $lockerPlan,
            'defaultStripeMode' => 'map',
        ]);
    }

    public function update(UpdateLockerPlanRequest $request, LockerPlan $lockerPlan): RedirectResponse
    {
        $priceId = $request->input('stripe_price_id') ?? $lockerPlan->stripe_price_id;

        if ($request->boolean('create_stripe_price')) {
            $priceId = $this->createStripePrice($request);
            if (! $priceId) {
                return redirect()->back()->with('flash', ['error' => 'Failed to create Stripe price. Check logs.']);
            }
        }

        $lockerPlan->update([
            'tier' => $request->input('tier'),
            'years' => (int) $request->input('years'),
            'file_size_mb' => $request->input('tier') === 'file' ? (int) $request->input('file_size_mb') : null,
            'amount_cents' => (int) $request->input('amount_cents'),
            'stripe_price_id' => $priceId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.locker-plans.index')->with('flash', ['success' => 'Locker plan updated.']);
    }

    public function destroy(LockerPlan $lockerPlan): RedirectResponse
    {
        $lockerPlan->delete();

        return redirect()->route('admin.locker-plans.index')->with('flash', ['success' => 'Locker plan deleted.']);
    }

    private function createStripePrice(StoreLockerPlanRequest|UpdateLockerPlanRequest $request): ?string
    {
        $tier = $request->input('tier');
        $years = (int) $request->input('years');
        $cents = (int) $request->input('amount_cents');

        $name = sprintf('eLocker — %s — %d yr%s', ucfirst($tier), $years, $years > 1 ? 's' : '');

        try {
            $product = Cashier::stripe()->products->create(['name' => $name]);

            $price = Cashier::stripe()->prices->create([
                'unit_amount' => $cents,
                'currency' => config('cashier.currency', 'aud'),
                'product' => $product->id,
            ]);

            return $price->id;
        } catch (\Throwable $e) {
            Log::error('Failed to create Stripe price for locker plan', [
                'error' => $e->getMessage(),
                'tier' => $tier,
                'years' => $years,
            ]);

            return null;
        }
    }
}
