<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecureLine\CheckoutSecureLineRequest;
use App\Http\Requests\SecureLine\StoreCallSessionRequest;
use App\Models\CallSession;
use App\Models\SecureLineCredit;
use App\Models\SecureLineProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class SecureLineCheckoutController extends Controller
{
    public function buy(): Response
    {
        $products = SecureLineProduct::where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->orderBy('amount_cents')
            ->get();

        return Inertia::render('Call/Buy', ['products' => $products]);
    }

    public function checkout(CheckoutSecureLineRequest $request): RedirectResponse|HttpResponse
    {
        $product = SecureLineProduct::where('id', $request->product_id)
            ->where('is_active', true)
            ->whereNotNull('stripe_price_id')
            ->firstOrFail();

        try {
            $session = Cashier::stripe()->checkout->sessions->create([
                'mode' => 'payment',
                'allow_promotion_codes' => true,
                'line_items' => [['price' => $product->stripe_price_id, 'quantity' => 1]],
                'metadata' => [
                    'product_type' => 'secure_line',
                    'secure_line_product_id' => $product->id,
                ],
                'success_url' => route('calls.await-credit').'?session={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('calls.buy'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed for SecureLine', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);

            return redirect()->route('calls.buy')->with('error', 'Payment service unavailable. Please try again.');
        }

        return Inertia::location($session->url);
    }

    public function awaitCredit(Request $request): Response
    {
        return Inertia::render('Call/AwaitCredit', [
            'session_id' => $request->query('session'),
        ]);
    }

    public function creditStatus(Request $request): JsonResponse
    {
        $sessionId = $request->query('session');

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session parameter.'], 422);
        }

        $credit = SecureLineCredit::where('stripe_session_id', $sessionId)->first();

        if (! $credit) {
            return response()->json(['pending' => true]);
        }

        return response()->json(['token' => $credit->token]);
    }

    public function create(Request $request): Response
    {
        $token = $request->query('token');
        $credit = SecureLineCredit::with(['secureLineProduct' => fn ($q) => $q->withTrashed()])
            ->where('token', $token)
            ->unused()
            ->first();

        if (! $credit || ! $credit->secureLineProduct) {
            abort(404);
        }

        return Inertia::render('Call/Create', [
            'credit_token' => $credit->token,
            'product' => [
                'name' => $credit->secureLineProduct->name,
                'duration_minutes' => $credit->secureLineProduct->duration_minutes,
                'max_participants' => $credit->secureLineProduct->max_participants,
            ],
        ]);
    }

    public function store(StoreCallSessionRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $credit = SecureLineCredit::where('token', $request->credit_token)
                ->whereNull('used_at')
                ->lockForUpdate()
                ->with(['secureLineProduct' => fn ($q) => $q->withTrashed()])
                ->first();

            if (! $credit) {
                return response()->json(['error' => 'Invalid or used credit token.'], 422);
            }

            $product = $credit->secureLineProduct;

            if (! $product) {
                return response()->json(['error' => 'Product not found.'], 422);
            }

            $session = CallSession::create([
                'public_key' => $request->public_key,
                'key_salt' => $request->key_salt,
                'starts_at' => now(),
                'ends_at' => now()->addMinutes($product->duration_minutes),
                'max_participants' => $product->max_participants,
            ]);

            $credit->update([
                'call_session_id' => $session->id,
                'used_at' => now(),
            ]);

            return response()->json([
                'bridge_number' => $session->hash_id,
                'starts_at' => $session->starts_at->toIso8601String(),
                'ends_at' => $session->ends_at->toIso8601String(),
            ]);
        });
    }
}
