<?php

namespace App\Listeners;

use App\Models\SecureLineCredit;
use App\Models\SecureLineProduct;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class HandleSecureLineStripeWebhook
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;

        if ($payload['type'] !== 'checkout.session.completed') {
            return;
        }

        $session = $payload['data']['object'];

        if (($session['metadata']['product_type'] ?? null) !== 'secure_line') {
            return;
        }

        // Guard: accept paid (normal) and no_payment_required (100%-off coupon); reject unpaid (async methods).
        $paymentStatus = $session['payment_status'] ?? null;

        if ($paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
            Log::info('SecureLine webhook: skipping non-paid session', [
                'stripe_session_id' => $session['id'],
                'payment_status' => $paymentStatus,
            ]);

            return;
        }

        if (SecureLineCredit::where('stripe_session_id', $session['id'])->exists()) {
            return;
        }

        $productId = $session['metadata']['secure_line_product_id'] ?? null;

        if (! $productId) {
            Log::warning('SecureLine webhook: missing secure_line_product_id', [
                'stripe_session_id' => $session['id'],
            ]);

            return;
        }

        if (! SecureLineProduct::where('id', $productId)->exists()) {
            Log::warning('SecureLine webhook: secure_line_product_id not found in database', [
                'stripe_session_id' => $session['id'],
                'secure_line_product_id' => $productId,
            ]);

            return;
        }

        SecureLineCredit::create([
            'token' => bin2hex(random_bytes(32)),
            'stripe_session_id' => $session['id'],
            'secure_line_product_id' => $productId,
        ]);
    }
}
