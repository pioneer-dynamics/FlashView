<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Models\User;
use App\Notifications\NewHigherValuePlanNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyUsersOfHigherValuePlan implements ShouldQueue
{
    use Queueable;

    public function __construct(public Plan $plan) {}

    public function handle(): void
    {
        $lowerPricePlans = Plan::where('price_per_month', '<', $this->plan->price_per_month)->get();

        if ($lowerPricePlans->isEmpty()) {
            return;
        }

        $lowerPriceIds = $lowerPricePlans
            ->flatMap(fn (Plan $p) => [$p->stripe_monthly_price_id, $p->stripe_yearly_price_id])
            ->filter()
            ->values();

        $notification = new NewHigherValuePlanNotification($this->plan);

        // Notify subscribed users on lower-value plans.
        // This app uses single-price subscriptions exclusively (via PlanController::subscribe()),
        // so stripe_price on the subscriptions row is always non-null for active subscriptions.
        // If multi-price subscriptions are introduced, this query must also check subscription_items.
        if ($lowerPriceIds->isNotEmpty()) {
            User::whereNotNull('email_verified_at')
                ->whereNull('suspended_at')
                ->whereHas('subscriptions', fn ($q) => $q->active()->whereIn('stripe_price', $lowerPriceIds))
                ->each(fn (User $user) => $user->notify(clone $notification));
        }

        // Notify free-plan users (no active subscription) when the free plan is lower value.
        // These two queries are mutually exclusive: a user either has an active subscription or does not.
        $freePlan = $lowerPricePlans->firstWhere('is_free_plan', true);

        if ($freePlan) {
            User::whereNotNull('email_verified_at')
                ->whereNull('suspended_at')
                ->whereDoesntHave('subscriptions', fn ($q) => $q->active())
                ->each(fn (User $user) => $user->notify(clone $notification));
        }
    }
}
