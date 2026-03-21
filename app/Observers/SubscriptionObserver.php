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
            }
        }
    }

    public function deleted(Subscription $subscription): void
    {
        $subscription->user->tokens()->delete();
    }
}
