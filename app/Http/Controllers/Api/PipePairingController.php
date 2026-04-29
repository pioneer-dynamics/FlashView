<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterPipeDeviceRequest;
use App\Http\Requests\Api\SendPipeSeedRequest;
use App\Models\PipeDevice;
use App\Models\PipePairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PipePairingController extends Controller
{
    /**
     * Register a device's public key for pairing.
     */
    public function registerDevice(RegisterPipeDeviceRequest $request): JsonResponse
    {
        $deviceId = 'DEV'.strtoupper(Str::random(4));

        while (PipeDevice::where('device_id', $deviceId)->exists()) {
            $deviceId = 'DEV'.strtoupper(Str::random(4));
        }

        $device = PipeDevice::create([
            'user_id' => $request->user()->id,
            'device_id' => $deviceId,
            'public_key' => $request->public_key,
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json([
            'device_id' => $device->device_id,
            'expires_at' => $device->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * List devices waiting to be paired (no accepted pairing yet, not expired).
     */
    public function waitingDevices(Request $request): JsonResponse
    {
        $devices = PipeDevice::where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->whereDoesntHave('pairings', fn ($q) => $q->where('is_accepted', true))
            ->get(['device_id', 'public_key', 'created_at']);

        return response()->json(['devices' => $devices]);
    }

    /**
     * De-register a device (used when receiver rejects the pairing code).
     */
    public function destroyDevice(Request $request, string $deviceId): JsonResponse
    {
        $device = PipeDevice::where('device_id', $deviceId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $device->delete();

        return response()->json(null, 204);
    }

    /**
     * Send an ECIES-encrypted pipe seed to a waiting device.
     */
    public function sendSeed(SendPipeSeedRequest $request): JsonResponse
    {
        $senderDevice = PipeDevice::where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        abort_if(! $senderDevice, 404, 'No sender device registered. Run flashview pipe setup first.');

        $receiverDevice = PipeDevice::where('device_id', $request->receiver_device_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $pairing = PipePairing::create([
            'sender_device_id' => $senderDevice->id,
            'receiver_device_id' => $receiverDevice->id,
            'encrypted_seed' => $request->encrypted_seed,
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json(['pairing_id' => $pairing->id], 201);
    }

    /**
     * Poll for an incoming pairing offer for a specific device.
     */
    public function pendingSeed(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => ['required', 'string'],
        ]);

        $device = PipeDevice::where('device_id', $request->device_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $pairing = PipePairing::with('senderDevice')
            ->where('receiver_device_id', $device->id)
            ->where('is_accepted', false)
            ->where('expires_at', '>', now())
            ->oldest()
            ->first();

        if (! $pairing) {
            return response()->json(null, 204);
        }

        return response()->json([
            'pairing_id' => $pairing->id,
            'sender_device_id' => $pairing->senderDevice->device_id,
            'sender_public_key' => $pairing->senderDevice->public_key,
            'encrypted_seed' => $pairing->encrypted_seed,
        ]);
    }

    /**
     * Get pairing status (sender polls to detect receiver acceptance).
     */
    public function show(Request $request, int $pairing): JsonResponse
    {
        $pairingRecord = PipePairing::with(['senderDevice', 'receiverDevice'])
            ->where('id', $pairing)
            ->where(function ($q) use ($request) {
                $q->whereHas('senderDevice', fn ($q) => $q->where('user_id', $request->user()->id))
                    ->orWhereHas('receiverDevice', fn ($q) => $q->where('user_id', $request->user()->id));
            })
            ->firstOrFail();

        return response()->json([
            'pairing_id' => $pairingRecord->id,
            'is_accepted' => $pairingRecord->is_accepted,
            'expires_at' => $pairingRecord->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Mark a pairing as accepted (receiver confirms the pairing code).
     */
    public function accept(Request $request, int $pairing): JsonResponse
    {
        $pairingRecord = PipePairing::with('receiverDevice')
            ->where('id', $pairing)
            ->whereHas('receiverDevice', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $pairingRecord->update(['is_accepted' => true]);

        return response()->json(['accepted' => true]);
    }
}
