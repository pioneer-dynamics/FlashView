<?php

namespace App\Http\Controllers;

use App\Facades\Turn;
use App\Http\Requests\Call\JoinCallSessionRequest;
use App\Models\CallSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CallSessionController extends Controller
{
    public function challenge(CallSession $callSession): JsonResponse
    {
        $challenge = bin2hex(random_bytes(32));
        cache()->put("call-challenge:{$callSession->id}", $challenge, 60);

        return response()->json([
            'challenge' => $challenge,
            'salt' => $callSession->key_salt,
        ]);
    }

    public function join(JoinCallSessionRequest $request, CallSession $callSession): JsonResponse
    {
        $challenge = cache()->pull("call-challenge:{$callSession->id}");

        if (! $challenge) {
            return response()->json(['message' => 'Challenge expired or not found. Request a new challenge.'], 422);
        }

        $signature = base64_decode($request->input('signature'));
        $publicKey = base64_decode($callSession->public_key);

        try {
            $valid = sodium_crypto_sign_verify_detached($signature, hex2bin($challenge), $publicKey);
        } catch (\SodiumException) {
            $valid = false;
        }

        if (! $valid) {
            return response()->json(['message' => 'Unauthorised'], 401);
        }

        $participant = $callSession->participants()->create([
            'joined_at' => now(),
            'ip_address' => $request->ip(),
            'public_key' => $request->input('public_key'),
        ]);

        $remainingSeconds = max(60, (int) now()->diffInSeconds($callSession->ends_at, absolute: true));

        try {
            $iceServers = Turn::getIceServers($remainingSeconds);
        } catch (\RuntimeException $e) {
            Log::warning('TURN provider failed during join', ['error' => $e->getMessage()]);
            $iceServers = [];
        }

        return response()->json([
            'session' => [
                'bridge_number' => $callSession->hash_id,
                'starts_at' => $callSession->starts_at,
                'ends_at' => $callSession->ends_at,
                'max_participants' => $callSession->max_participants,
                'current_participant_count' => $callSession->participants()->count(),
            ],
            'participant_id' => $participant->id,
            'ice_servers' => $iceServers,
            'turn_available' => ! empty($iceServers),
        ]);
    }
}
