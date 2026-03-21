<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BurnSecretRequest;
use App\Http\Requests\Api\ListSecretsRequest;
use App\Http\Requests\Api\StoreSecretRequest;
use App\Http\Resources\SecretResource;
use App\Models\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

class SecretController extends Controller
{
    /**
     * List authenticated user's secrets.
     */
    public function index(ListSecretsRequest $request): JsonResponse
    {
        $secrets = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate());

        return SecretResource::collection($secrets)->response();
    }

    /**
     * Create a new secret and return the web signed URL.
     */
    public function store(StoreSecretRequest $request): JsonResponse
    {
        $secret = Secret::create([
            'message' => $request->message,
            'expires_at' => $expiresAt = now()->addMinutes((int) $request->expires_in),
            'user_id' => $request->user()->id,
        ]);

        $url = URL::temporarySignedRoute('secret.show', $expiresAt, ['secret' => $secret->hash_id]);

        return (new SecretResource($secret))
            ->additional(['data' => ['url' => $url]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Burn (delete) a secret.
     */
    public function destroy(BurnSecretRequest $request, string $secret): JsonResponse
    {
        $secretRecord = $request->getSecretRecord();
        $secretRecord->markSilentlyAsRetrieved();

        return response()->json(['message' => 'Secret burned successfully.']);
    }
}
