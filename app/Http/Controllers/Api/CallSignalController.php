<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Call\CreateCallSignalRequest;
use App\Http\Requests\Call\ListCallSignalsRequest;
use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\CallSignal;
use Illuminate\Http\JsonResponse;

class CallSignalController extends Controller
{
    public function participants(CallSession $callSession): JsonResponse
    {
        if (! $callSession->isActive()) {
            return response()->json(['message' => 'Call session has ended.'], 404);
        }

        $participants = $callSession->participants()
            ->whereNull('left_at')
            ->get(['id', 'joined_at', 'public_key']);

        return response()->json(['participants' => $participants]);
    }

    public function store(CreateCallSignalRequest $request, CallSession $callSession): JsonResponse
    {
        if (! $callSession->isActive()) {
            return response()->json(['message' => 'Call session has ended.'], 404);
        }

        $fromExists = CallParticipant::where('id', $request->from_participant_id)
            ->where('call_session_id', $callSession->id)
            ->exists();

        $toExists = CallParticipant::where('id', $request->to_participant_id)
            ->where('call_session_id', $callSession->id)
            ->exists();

        if (! $fromExists || ! $toExists) {
            return response()->json(['message' => 'Invalid participant ID for this session.'], 422);
        }

        $signal = CallSignal::create([
            'call_session_id' => $callSession->id,
            'from_participant_id' => $request->from_participant_id,
            'to_participant_id' => $request->to_participant_id,
            'type' => $request->type,
            'payload' => $request->payload,
        ]);

        return response()->json(['signal_id' => $signal->id], 201);
    }

    public function index(ListCallSignalsRequest $request, CallSession $callSession): JsonResponse
    {
        if (! $callSession->isActive()) {
            return response()->json(['message' => 'Call session has ended.'], 404);
        }

        $participantExists = CallParticipant::where('id', $request->participant_id)
            ->where('call_session_id', $callSession->id)
            ->exists();

        if (! $participantExists) {
            return response()->json(['message' => 'Invalid participant ID for this session.'], 422);
        }

        $signals = CallSignal::where('call_session_id', $callSession->id)
            ->where('to_participant_id', $request->participant_id)
            ->where('id', '>', (int) ($request->after ?? 0))
            ->orderBy('id')
            ->get(['id', 'from_participant_id', 'type', 'payload']);

        return response()->json(['signals' => $signals]);
    }
}
