<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSecretsRequest;
use App\Http\Requests\Api\RetrieveSecretRequest;
use App\Http\Requests\Api\ShowSecretMetadataRequest;
use App\Http\Requests\BurnSecretRequest;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Resources\SecretMessageResource;
use App\Http\Resources\SecretResource;
use App\Services\SecretService;
use Illuminate\Http\JsonResponse;

class SecretController extends Controller
{
    public function __construct(private SecretService $secretService) {}

    /**
     * List authenticated user's secrets.
     */
    public function index(ListSecretsRequest $request): JsonResponse
    {
        $secrets = $this->secretService->listSecrets($request->user());

        return SecretResource::collection($secrets)->response();
    }

    /**
     * Create a new secret and return the web signed URL.
     */
    public function store(StoreSecretRequest $request): JsonResponse
    {
        $result = $this->secretService->createSecret(
            $request->message,
            (int) $request->expires_in,
            $request->user()->id,
        );

        if ($email = $request->safe()->email) {
            $this->secretService->notifyRecipient($request->user(), $email, $result['url'], $result['secret']->hash_id);
        }

        return (new SecretResource($result['secret']))
            ->additional(['data' => ['url' => $result['url']]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show metadata for a secret.
     */
    public function show(ShowSecretMetadataRequest $request, string $secret): JsonResponse
    {
        return (new SecretResource($request->getSecretRecord()))->response();
    }

    /**
     * Retrieve a secret's encrypted message (one-time access).
     *
     * Uses a Form Request instead of route model binding to prevent
     * the `retrieved` Eloquent event from firing before authorization.
     * The secret is loaded with withoutEvents in the request, then
     * consumed here after authorization succeeds.
     */
    public function retrieve(RetrieveSecretRequest $request, string $secret): JsonResponse
    {
        $secretRecord = $request->getSecretRecord();

        if (! $secretRecord) {
            abort(404, 'Secret not found.');
        }

        $message = $secretRecord->message;

        $secretRecord->markSilentlyAsRetrieved();

        return (new SecretMessageResource([
            'hash_id' => $secretRecord->hash_id,
            'message' => $message,
        ]))->response();
    }

    /**
     * Burn (delete) a secret.
     */
    public function destroy(BurnSecretRequest $request, string $secret): JsonResponse
    {
        $this->secretService->burnSecret($request->getSecretRecord());

        return response()->json(['message' => 'Secret burned successfully.']);
    }
}
