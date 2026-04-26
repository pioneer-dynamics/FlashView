<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UnsubscribeRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = PlanResource::collection(Plan::get()->sortBy('price_per_month'));

        return Inertia::render('Plan/Index', compact('plans'));
    }

    public function unsubscribe(UnsubscribeRequest $request): RedirectResponse
    {
        $request->user()->subscription('default')->cancel();

        return redirect()->route('plans.index');
    }

    public function resume(Request $request)
    {
        $request->user()->subscription('default')->resume();
    }

    public function subscribe(Request $request, Plan $plan, $period)
    {
        if (! $plan->isCurrentlyAvailable()) {
            $reason = $plan->start_date && now()->startOfDay()->lt($plan->start_date)
                ? 'This plan is not yet available.'
                : 'This plan is no longer available for subscription.';

            return redirect()->route('plans.index')->with('flash', [
                'banner' => $reason,
                'bannerStyle' => 'danger',
            ]);
        }

        $user = $request->user();

        $price_id = match ($period) {
            'yearly' => $plan->stripe_yearly_price_id,
            'monthly' => $plan->stripe_monthly_price_id,
        };

        if ($user->subscriptions()->active()->count() == 0) {
            return $user
                ->newSubscription('default', $price_id)
                ->allowPromotionCodes()
                ->checkout([
                    'success_url' => route('payment.confirming').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('dashboard'),
                ]);
        } else {
            $user->subscription('default')->swap($price_id);

            return redirect()->route('plans.index');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlanRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Plan $plan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Plan $plan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        //
    }
}
