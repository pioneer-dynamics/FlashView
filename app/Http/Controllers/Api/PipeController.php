<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompletePipeSessionRequest;
use App\Http\Requests\Api\CreatePipeSessionRequest;
use App\Http\Requests\Api\UploadPipeChunkRequest;
use App\Models\PipeChunk;
use App\Models\PipeSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipeController extends Controller
{
    /**
     * Create a new pipe session.
     */
    public function store(CreatePipeSessionRequest $request): JsonResponse
    {
        $session = PipeSession::create([
            'session_id' => $request->session_id,
            'user_id' => $request->user()?->id,
            'transfer_mode' => $request->transfer_mode,
            'expires_at' => now()->addSeconds(config('pipe.session_ttl_seconds')),
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
            'chunk_count' => $session->chunks()->count(),
            'total_chunks' => $session->total_chunks,
            'expires_at' => $session->expires_at->toIso8601String(),
            'transfer_mode' => $session->transfer_mode,
        ]);
    }

    /**
     * Upload an encrypted chunk.
     */
    public function uploadChunk(UploadPipeChunkRequest $request, string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        if ($session->chunks()->count() >= config('pipe.max_chunks_per_session')) {
            return response()->json(['error' => 'max_chunks_exceeded'], 422);
        }

        $existingChunk = PipeChunk::where('pipe_session_id', $session->id)
            ->where('chunk_index', $request->chunk_index)
            ->first();

        if ($existingChunk) {
            return response()->json(['error' => 'chunk_already_exists'], 409);
        }

        PipeChunk::create([
            'pipe_session_id' => $session->id,
            'chunk_index' => $request->chunk_index,
            'payload' => $request->payload,
        ]);

        return response()->json(['chunk_index' => $request->chunk_index], 201);
    }

    /**
     * Download a chunk; returns 202 if not yet available.
     */
    public function downloadChunk(string $sessionId, int $index): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $chunk = PipeChunk::where('pipe_session_id', $session->id)
            ->where('chunk_index', $index)
            ->first();

        if (! $chunk) {
            return response()->json(['status' => 'pending'], 202);
        }

        return response()->json(['payload' => $chunk->payload]);
    }

    /**
     * Mark upload complete after verifying chunk count matches.
     */
    public function complete(CompletePipeSessionRequest $request, string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $actualCount = $session->chunks()->count();

        if ($actualCount !== (int) $request->total_chunks) {
            return response()->json([
                'error' => 'chunk_count_mismatch',
                'message' => "Expected {$request->total_chunks} chunks but found {$actualCount}.",
            ], 422);
        }

        $session->update([
            'is_complete' => true,
            'total_chunks' => $request->total_chunks,
        ]);

        return response()->json([
            'total_chunks' => $session->total_chunks,
            'is_complete' => true,
        ]);
    }

    /**
     * Burn (delete) a session and all its chunks.
     */
    public function destroy(Request $request, string $sessionId): JsonResponse
    {
        $session = PipeSession::where('session_id', $sessionId)->first();

        if ($session) {
            $session->delete();
        }

        return response()->json(null, 204);
    }
}
