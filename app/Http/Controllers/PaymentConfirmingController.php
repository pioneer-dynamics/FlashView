<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentConfirmingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->subscriptions()->active()->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Plan/PaymentConfirming', [
            'sessionId' => $request->query('session_id'),
        ]);
    }
}
