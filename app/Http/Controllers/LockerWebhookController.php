<?php

namespace App\Http\Controllers;

use App\Models\Locker;
use App\Models\LockerCredit;
use App\Models\LockerRenewal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class LockerWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('lockers.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Locker webhook signature verification failed', ['error' => $e->getMessage()]);

            return response('Signature verification failed', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response('OK', 200);
        }

        $session = $event->data->object;
        $action = $session->metadata->action ?? null;

        match ($action) {
            'create' => $this->handleCreate($session),
            'renewal' => $this->handleRenewal($session),
            default => Log::info('Locker webhook: unhandled action', ['action' => $action]),
        };

        return response('OK', 200);
    }

    private function handleCreate(object $session): void
    {
        if (LockerCredit::where('stripe_session_id', $session->id)->exists()) {
            return;
        }

        LockerCredit::create([
            'token' => bin2hex(random_bytes(32)),
            'tier' => $session->metadata->tier,
            'years' => (int) $session->metadata->years,
            'stripe_session_id' => $session->id,
        ]);
    }

    private function handleRenewal(object $session): void
    {
        if (LockerRenewal::where('stripe_session_id', $session->id)->exists()) {
            return;
        }

        $accountId = $session->metadata->account_id ?? null;

        if (! $accountId) {
            Log::warning('Locker renewal webhook missing account_id', ['session_id' => $session->id]);

            return;
        }

        DB::transaction(function () use ($session, $accountId) {
            $locker = Locker::where('account_id', $accountId)->first();

            if (! $locker) {
                Log::warning('Locker renewal: locker not found', ['account_id' => $accountId]);

                return;
            }

            $years = (int) $session->metadata->years;

            $locker->expires_at = Carbon::parse(
                max($locker->expires_at->timestamp, now()->timestamp),
                'UTC'
            )->addYears($years);

            $locker->auth_challenge = bin2hex(random_bytes(32));
            $locker->save();

            LockerRenewal::create([
                'stripe_session_id' => $session->id,
                'account_id' => $accountId,
                'years' => $years,
                'processed_at' => now(),
                'created_at' => now(),
            ]);
        });
    }
}
