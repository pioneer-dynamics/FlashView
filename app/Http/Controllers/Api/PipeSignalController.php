<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatePipeSignalRequest;
use App\Models\PipeSession;
use App\Models\PipeSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipeSignalController extends Controller
{
    /**
     * Store a WebRTC signaling message.
     */
    public function store(CreatePipeSignalRequest $request, string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $signal = PipeSignal::create([
            'pipe_session_id' => $session->id,
            'role' => $request->role,
            'type' => $request->type,
            'payload' => $request->payload,
        ]);

        return response()->json(['signal_id' => $signal->id], 201);
    }

    /**
     * Poll for WebRTC signals for a given role after a given ID.
     */
    public function index(Request $request, string $sessionId): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:sender,receiver'],
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $signals = PipeSignal::where('pipe_session_id', $session->id)
            ->where('role', $request->role)
            ->where('id', '>', (int) ($request->after ?? 0))
            ->orderBy('id')
            ->get(['id', 'type', 'payload']);

        return response()->json(['signals' => $signals]);
    }
}
