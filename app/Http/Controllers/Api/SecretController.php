<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSecretsRequest;
use App\Http\Requests\BurnSecretRequest;
use App\Http\Requests\StoreSecretRequest;
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
     * Burn (delete) a secret.
     */
    public function destroy(BurnSecretRequest $request, string $secret): JsonResponse
    {
        $this->secretService->burnSecret($request->getSecretRecord());

        return response()->json(['message' => 'Secret burned successfully.']);
    }
}
