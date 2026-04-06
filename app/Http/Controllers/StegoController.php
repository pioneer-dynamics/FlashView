<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StegoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Secret/StegoPage', [
            'canUseStego' => $user && $user->planSupportsStego(),
        ]);
    }
}
