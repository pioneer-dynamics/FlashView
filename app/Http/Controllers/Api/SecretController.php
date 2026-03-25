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
use App\Models\Secret;
use App\Services\SecretService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SecretController extends Controller implements HasMiddleware
{
    public function __construct(private SecretService $secretService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('throttle:api-secrets', only: ['store']),
        ];
    }

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
     * The Form Request gates on secrets:list token ability before
     * the model is loaded. Secret::findByHashID triggers the normal
     * Eloquent retrieved event, which marks the secret as consumed
     * and notifies the owner. ActiveScope ensures expired or
     * already-retrieved secrets return 404 automatically.
     */
    public function retrieve(RetrieveSecretRequest $request, string $secret): JsonResponse
    {
        $secretRecord = Secret::findByHashID($secret);

        return (new SecretMessageResource([
            'hash_id' => $secretRecord->hash_id,
            'message' => $secretRecord->message,
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
