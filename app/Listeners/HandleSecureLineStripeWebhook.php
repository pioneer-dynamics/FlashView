<?php

namespace App\Listeners;

use App\Models\SecureLineCredit;
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

        // Guard: async payment methods (SEPA, BACS) fire completed before money clears.
        if (($session['payment_status'] ?? null) !== 'paid') {
            Log::info('SecureLine webhook: skipping non-paid session', [
                'stripe_session_id' => $session['id'],
                'payment_status' => $session['payment_status'] ?? null,
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

        SecureLineCredit::create([
            'token' => bin2hex(random_bytes(32)),
            'stripe_session_id' => $session['id'],
            'secure_line_product_id' => $productId,
        ]);
    }
}
