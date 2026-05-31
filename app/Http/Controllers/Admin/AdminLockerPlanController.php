<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLockerPlanRequest;
use App\Http\Requests\UpdateLockerPlanRequest;
use App\Models\LockerPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminLockerPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/LockerPlans/Index', [
            'plans' => LockerPlan::orderBy('tier')->orderBy('years')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/LockerPlans/Form', [
            'plan' => null,
        ]);
    }

    public function store(StoreLockerPlanRequest $request): RedirectResponse
    {
        LockerPlan::create([
            'tier' => $request->input('tier'),
            'years' => (int) $request->input('years'),
            'amount_cents' => (int) $request->input('amount_cents'),
            'stripe_price_id' => $request->input('stripe_price_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.locker-plans.index')->with('flash', ['success' => 'Locker plan created.']);
    }

    public function edit(LockerPlan $lockerPlan): Response
    {
        return Inertia::render('Admin/LockerPlans/Form', [
            'plan' => $lockerPlan,
        ]);
    }

    public function update(UpdateLockerPlanRequest $request, LockerPlan $lockerPlan): RedirectResponse
    {
        $lockerPlan->update([
            'tier' => $request->input('tier'),
            'years' => (int) $request->input('years'),
            'amount_cents' => (int) $request->input('amount_cents'),
            'stripe_price_id' => $request->input('stripe_price_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.locker-plans.index')->with('flash', ['success' => 'Locker plan updated.']);
    }

    public function destroy(LockerPlan $lockerPlan): RedirectResponse
    {
        $lockerPlan->delete();

        return redirect()->route('admin.locker-plans.index')->with('flash', ['success' => 'Locker plan deleted.']);
    }
}
