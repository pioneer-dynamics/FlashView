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
use App\Services\EmailMaskingService;
use App\Services\SecretService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecretController extends Controller implements HasMiddleware
{
    public function __construct(
        private SecretService $secretService,
        private EmailMaskingService $emailMaskingService,
    ) {}

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
        $maskedRecipientEmail = null;
        $senderCompanyName = null;
        $senderDomain = null;
        $senderEmail = null;

        if ($request->user()->store_masked_recipient_email && $email = $request->safe()->email) {
            $maskedRecipientEmail = $this->emailMaskingService->mask($email);
        }

        if ($request->boolean('include_sender_identity') && $request->user()->planSupportsSenderIdentity() && $request->user()->hasVerifiedSenderIdentity()) {
            $identity = $request->user()->senderIdentity;
            $senderCompanyName = $identity->isDomainType() ? $identity->company_name : null;
            $senderDomain = $identity->isDomainType() ? $identity->domain : null;
            $senderEmail = $identity->isEmailType() ? $identity->email : null;
        }

        $result = $this->secretService->createSecret(
            $request->message,
            (int) $request->expires_in,
            $request->user()->id,
            $maskedRecipientEmail,
            $senderCompanyName,
            $senderDomain,
            $senderEmail,
            $request->file('file'),
            null,
            $request->file_original_name,
            $request->filled('file_size') ? (int) $request->file_size : null,
            $request->file_mime_type,
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
     * For file secrets, returns file metadata and signals the CLI to call downloadFile next.
     */
    public function retrieve(RetrieveSecretRequest $request, string $secret): JsonResponse
    {
        $secretRecord = Secret::findByHashID($secret);

        if ($secretRecord->isFileSecret()) {
            $isCombined = $secretRecord->message !== null;

            $data = [
                'hash_id' => $secretRecord->hash_id,
                'type' => $isCombined ? 'combined' : 'file',
                'filename' => $secretRecord->filename,
                'file_size' => $secretRecord->file_size,
                'file_mime_type' => $secretRecord->file_mime_type,
            ];

            if ($isCombined) {
                $data['message'] = $secretRecord->message;
                DB::table($secretRecord->getTable())->where('id', $secretRecord->id)->update(['message' => null]);
            }

            return response()->json(['data' => $data]);
        }

        return (new SecretMessageResource([
            'hash_id' => $secretRecord->hash_id,
            'message' => $secretRecord->message,
        ]))->response();
    }

    /**
     * Download an encrypted file secret (one-time, presigned URL or streaming fallback).
     */
    public function downloadFile(string $secret): RedirectResponse|StreamedResponse
    {
        return $this->secretService->downloadFileSecret($secret);
    }

    /**
     * Confirm that the client has downloaded the file and the S3 object can be deleted.
     */
    public function confirmFileDownloaded(string $secret): JsonResponse
    {
        $this->secretService->deleteDownloadedFile($secret);

        return response()->json(null, 204);
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
