<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecureLineProductRequest;
use App\Http\Requests\UpdateSecureLineProductRequest;
use App\Models\SecureLineProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;

class AdminSecureLineProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/SecureLineProducts/Index', [
            'products' => SecureLineProduct::orderBy('duration_minutes')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SecureLineProducts/Form', [
            'product' => null,
            'defaultStripeMode' => app()->environment('production') ? 'create' : 'map',
        ]);
    }

    public function store(StoreSecureLineProductRequest $request): RedirectResponse
    {
        $priceId = $request->input('stripe_price_id');

        if ($request->boolean('create_stripe_price')) {
            $priceId = $this->createStripePrice($request);
            if (! $priceId) {
                return redirect()->back()->with('flash', ['error' => 'Failed to create Stripe price. Check logs.']);
            }
        }

        SecureLineProduct::create([
            'name' => $request->input('name'),
            'duration_minutes' => (int) $request->input('duration_minutes'),
            'max_participants' => (int) $request->input('max_participants'),
            'amount_cents' => (int) $request->input('amount_cents'),
            'stripe_price_id' => $priceId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.secure-line-products.index')
            ->with('flash', ['success' => 'Secure Line product created.']);
    }

    public function edit(SecureLineProduct $secureLineProduct): Response
    {
        return Inertia::render('Admin/SecureLineProducts/Form', [
            'product' => $secureLineProduct,
            'defaultStripeMode' => 'map',
        ]);
    }

    public function update(UpdateSecureLineProductRequest $request, SecureLineProduct $secureLineProduct): RedirectResponse
    {
        // Fall back to the existing price ID if the admin doesn't re-enter it
        $priceId = $request->input('stripe_price_id') ?? $secureLineProduct->stripe_price_id;

        if ($request->boolean('create_stripe_price')) {
            $priceId = $this->createStripePrice($request);
            if (! $priceId) {
                return redirect()->back()->with('flash', ['error' => 'Failed to create Stripe price. Check logs.']);
            }
        }

        $secureLineProduct->update([
            'name' => $request->input('name'),
            'duration_minutes' => (int) $request->input('duration_minutes'),
            'max_participants' => (int) $request->input('max_participants'),
            'amount_cents' => (int) $request->input('amount_cents'),
            'stripe_price_id' => $priceId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.secure-line-products.index')
            ->with('flash', ['success' => 'Secure Line product updated.']);
    }

    public function destroy(SecureLineProduct $secureLineProduct): RedirectResponse
    {
        $secureLineProduct->delete();

        return redirect()->route('admin.secure-line-products.index')
            ->with('flash', ['success' => 'Secure Line product deleted.']);
    }

    protected function createStripePrice(StoreSecureLineProductRequest|UpdateSecureLineProductRequest $request): ?string
    {
        $name = $request->input('name');
        $cents = (int) $request->input('amount_cents');

        try {
            $product = Cashier::stripe()->products->create(['name' => $name]);

            $price = Cashier::stripe()->prices->create([
                'unit_amount' => $cents,
                'currency' => config('cashier.currency', 'aud'),
                'product' => $product->id,
                // No 'recurring' key — one-time price, not subscription
            ]);

            return $price->id;
        } catch (\Throwable $e) {
            Log::error('Failed to create Stripe price for Secure Line product', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);

            return null;
        }
    }
}
