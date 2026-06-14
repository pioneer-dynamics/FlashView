<?php

namespace App\Http\Controllers;

use App\Models\CallSession;
use Inertia\Inertia;
use Inertia\Response;

class CallPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Call/Index');
    }

    public function show(CallSession $callSession): Response
    {
        return Inertia::render('Call/Join', [
            'session' => [
                'bridge_number' => $callSession->hash_id,
                'starts_at' => $callSession->starts_at,
                'ends_at' => $callSession->ends_at,
                'is_active' => $callSession->isActive(),
            ],
        ]);
    }

    public function room(CallSession $callSession): Response
    {
        return Inertia::render('Call/Room', [
            'session' => [
                'bridge_number' => $callSession->hash_id,
                'ends_at' => $callSession->ends_at,
            ],
        ]);
    }
}
