<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = Plan::orderBy('price_per_month', 'asc')->get();

        return Inertia::render('Plan/Index', compact('plans'));
    }

    public function subscribe(Request $request, Plan $plan, $period)
    {
        $user = $request->user();

        $price_id = match($period) {
            'yearly' => $plan->stripe_yearly_price_id,
            'monthly' => $plan->stripe_monthly_price_id,
        };

        if($user->subscriptions()->active()->count() == 0) {
            return $user
                    ->newSubscription('default', $price_id)
                    ->allowPromotionCodes()
                    ->checkout([
                        'success_url' => route('dashboard'),
                        'cancel_url' => route('dashboard'),
                    ]);
        }
        else {
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
