<?php

namespace App\Listeners;

use App\Models\Locker;
use App\Models\LockerCredit;
use App\Models\LockerRenewal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class HandleLockerStripeWebhook
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;

        if ($payload['type'] !== 'checkout.session.completed') {
            return;
        }

        $session = $payload['data']['object'];
        $action = $session['metadata']['action'] ?? null;

        match ($action) {
            'create' => $this->handleCreate($session),
            'renewal' => $this->handleRenewal($session),
            default => null,
        };
    }

    private function handleCreate(array $session): void
    {
        // Guard: async payment methods and 100%-off coupons can produce non-paid sessions.
        if (($session['payment_status'] ?? null) !== 'paid' && ($session['payment_status'] ?? null) !== 'no_payment_required') {
            Log::info('Locker create webhook: skipping non-paid session', [
                'stripe_session_id' => $session['id'],
                'payment_status' => $session['payment_status'] ?? null,
            ]);

            return;
        }

        if (LockerCredit::where('stripe_session_id', $session['id'])->exists()) {
            return;
        }

        LockerCredit::create([
            'token' => bin2hex(random_bytes(32)),
            'tier' => $session['metadata']['tier'],
            'years' => (int) $session['metadata']['years'],
            'stripe_session_id' => $session['id'],
        ]);
    }

    private function handleRenewal(array $session): void
    {
        if (LockerRenewal::where('stripe_session_id', $session['id'])->exists()) {
            return;
        }

        $accountId = $session['metadata']['account_id'] ?? null;

        if (! $accountId) {
            Log::warning('Locker renewal webhook missing account_id', ['session_id' => $session['id']]);

            return;
        }

        DB::transaction(function () use ($session, $accountId) {
            $locker = Locker::where('account_id', $accountId)->first();

            if (! $locker) {
                Log::warning('Locker renewal: locker not found', ['account_id' => $accountId]);

                return;
            }

            $years = (int) $session['metadata']['years'];

            $locker->expires_at = Carbon::parse(
                max($locker->expires_at->timestamp, now()->timestamp),
                'UTC'
            )->addYears($years);

            $locker->save();

            LockerRenewal::create([
                'stripe_session_id' => $session['id'],
                'account_id' => $accountId,
                'years' => $years,
                'processed_at' => now(),
                'created_at' => now(),
            ]);
        });
    }
}
