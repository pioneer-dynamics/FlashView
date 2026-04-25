<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $allPlans = Plan::all();
        $freePlan = $allPlans->firstWhere('is_free_plan', true);
        $plansByStripePrice = [];
        foreach ($allPlans as $plan) {
            if ($plan->stripe_monthly_price_id) {
                $plansByStripePrice[$plan->stripe_monthly_price_id] = $plan;
            }
            if ($plan->stripe_yearly_price_id) {
                $plansByStripePrice[$plan->stripe_yearly_price_id] = $plan;
            }
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with(['subscriptions'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function (User $user) use ($freePlan, $plansByStripePrice) {
                    $subscription = $user->subscriptions->first();
                    $stripePrice = $subscription?->stripe_price;
                    $plan = $stripePrice ? ($plansByStripePrice[$stripePrice] ?? null) : $freePlan;

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'plan_name' => $plan?->name ?? '—',
                        'subscription_status' => $subscription?->stripe_status ?? '—',
                        'joined_at' => $user->created_at->toDateString(),
                    ];
                }),
        ]);
    }
}
