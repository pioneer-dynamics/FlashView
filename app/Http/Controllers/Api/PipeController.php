<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatePipeSessionRequest;
use App\Http\Requests\Api\PendingSessionsRequest;
use App\Models\PipeDevice;
use App\Models\PipeSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PipeController extends Controller
{
    /**
     * Create a new pipe session.
     */
    public function store(CreatePipeSessionRequest $request): JsonResponse
    {
        $ttl = $request->expires_in ?? config('pipe.session_ttl_seconds');

        $senderDeviceId = null;
        $receiverDeviceId = null;

        if ($request->sender_device_id) {
            $senderDevice = PipeDevice::where('device_id', $request->sender_device_id)
                ->where('user_id', $request->user()?->id)
                ->first();
            $senderDeviceId = $senderDevice?->id;
        }

        if ($request->receiver_device_id) {
            $receiverDevice = PipeDevice::where('device_id', $request->receiver_device_id)
                ->where('user_id', $request->user()?->id)
                ->first();
            $receiverDeviceId = $receiverDevice?->id;
        }

        $session = PipeSession::create([
            'session_id' => $request->session_id,
            'user_id' => $request->user()?->id,
            'sender_device_id' => $senderDeviceId,
            'receiver_device_id' => $receiverDeviceId,
            'encrypted_transfer_key' => $request->encrypted_transfer_key,
            'transfer_mode' => $request->transfer_mode,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return response()->json([
            'session_id' => $session->session_id,
            'expires_at' => $session->expires_at->toIso8601String(),
            'transfer_mode' => $session->transfer_mode,
        ], 201);
    }

    /**
     * Get session status.
     */
    public function show(string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return response()->json([
            'session_id' => $session->session_id,
            'is_complete' => $session->is_complete,
            'expires_at' => $session->expires_at->toIso8601String(),
            'transfer_mode' => $session->transfer_mode,
        ]);
    }

    /**
     * Poll for pending sessions addressed to this device (Task 17 receiver flow).
     */
    public function pendingSessions(PendingSessionsRequest $request): JsonResponse
    {
        $deviceId = $request->validated('device_id');

        $device = PipeDevice::where('device_id', $deviceId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $session = PipeSession::with(['senderDevice:id,device_id,public_key'])
            ->where('receiver_device_id', $device->id)
            ->where('expires_at', '>', now())
            ->oldest()
            ->first();

        if (! $session) {
            return response()->json(null, 204);
        }

        return response()->json([
            'session_id' => $session->session_id,
            'encrypted_transfer_key' => $session->encrypted_transfer_key,
            'sender_device_id' => $session->senderDevice?->device_id,
            'sender_public_key' => $session->senderDevice?->public_key,
            'expires_at' => $session->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Return a presigned S3 upload URL, or a signed server-side upload URL as fallback.
     */
    public function prepareUpload(string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_if($session->is_complete, 422, 'Session is already complete.');

        $storagePath = "pipe-payloads/{$sessionId}.bin";

        try {
            ['url' => $uploadUrl, 'headers' => $uploadHeaders] = Storage::temporaryUploadUrl(
                $storagePath,
                now()->addMinutes(15),
                ['ContentType' => 'application/octet-stream']
            );

            return response()->json([
                'upload_type' => 's3_direct',
                'upload_url' => $uploadUrl,
                'upload_headers' => $uploadHeaders,
            ]);
        } catch (\RuntimeException) {
            return response()->json([
                'upload_type' => 'server',
                'upload_url' => route('api.v1.pipe.payload.upload', ['sessionId' => $sessionId]),
                'upload_headers' => [],
            ]);
        }
    }

    /**
     * Server-side fallback: receive raw encrypted binary and store it.
     */
    public function serverUpload(Request $request, string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_if($session->is_complete, 422, 'Session is already complete.');

        $storagePath = "pipe-payloads/{$sessionId}.bin";
        Storage::put($storagePath, $request->getContent());

        return response()->json(['status' => 'ok']);
    }

    /**
     * Mark upload complete.
     */
    public function complete(string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $storagePath = "pipe-payloads/{$sessionId}.bin";

        abort_unless(Storage::exists($storagePath), 422, 'Payload has not been uploaded yet.');

        $session->update([
            'is_complete' => true,
            'storage_path' => $storagePath,
        ]);

        return response()->json(['is_complete' => true]);
    }

    /**
     * Download the encrypted payload — redirects to a presigned S3 URL or streams from local disk.
     */
    public function download(string $sessionId): mixed
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_unless($session->is_complete, 202, 'Transfer not yet complete.');
        abort_unless($session->storage_path && Storage::exists($session->storage_path), 404, 'Payload not found.');

        try {
            $url = Storage::temporaryUrl($session->storage_path, now()->addMinutes(5));

            return redirect($url);
        } catch (\RuntimeException) {
            return response(Storage::get($session->storage_path), 200, [
                'Content-Type' => 'application/octet-stream',
            ]);
        }
    }

    /**
     * Burn (delete) a session and its stored payload.
     */
    public function destroy(string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)->first();

        if ($session) {
            $session->delete();
        }

        return response()->json(null, 204);
    }
}
