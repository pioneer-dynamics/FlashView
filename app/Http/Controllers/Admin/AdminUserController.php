<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with(['subscriptions'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'plan_name' => $user->resolvePlan()?->name ?? '—',
                    'subscription_status' => $user->subscription?->stripe_status ?? '—',
                    'joined_at' => $user->created_at->toDateString(),
                ]),
        ]);
    }
}
