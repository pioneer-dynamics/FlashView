<?php

namespace App\Observers;

use Laravel\Cashier\Subscription;

class SubscriptionObserver
{
    public function updated(Subscription $subscription): void
    {
        if ($subscription->isDirty('stripe_price') || $subscription->isDirty('ends_at') || $subscription->isDirty('stripe_status')) {
            $user = $subscription->user;

            if (! $user->hasApiAccess()) {
                $user->tokens()->delete();

                if ($user->hasWebhookConfigured()) {
                    $user->updateQuietly([
                        'webhook_url' => null,
                        'webhook_secret' => null,
                    ]);
                }
            }
        }
    }

    public function deleted(Subscription $subscription): void
    {
        $user = $subscription->user;

        $user->tokens()->delete();

        if ($user->hasWebhookConfigured()) {
            $user->updateQuietly([
                'webhook_url' => null,
                'webhook_secret' => null,
            ]);
        }
    }
}
