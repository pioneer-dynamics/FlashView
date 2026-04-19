<?php

namespace App\Http\Controllers;

use App\Http\Requests\BurnSecretRequest;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Resources\SecretResourceCollection;
use App\Models\Secret;
use App\Services\EmailMaskingService;
use App\Services\SecretService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
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
            new Middleware('signed', only: ['show', 'decrypt', 'downloadFile', 'confirmFileDownloaded']),
            new Middleware('throttle:secrets', only: ['store']),
        ];
    }

    public function report(string $secret): void
    {
        $secret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find(Secret::decodeHashId($secret)));

        // collect details from recipient as proof of spam or abuse.
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSecretRequest $request): RedirectResponse
    {
        $maskedRecipientEmail = null;
        $senderCompanyName = null;
        $senderDomain = null;
        $senderEmail = null;

        if ($request->user()?->store_masked_recipient_email && $email = $request->safe()->email) {
            $maskedRecipientEmail = $this->emailMaskingService->mask($email);
        }

        if ($request->boolean('include_sender_identity') && $request->user()?->planSupportsSenderIdentity() && $request->user()?->hasVerifiedSenderIdentity()) {
            $identity = $request->user()->senderIdentity;
            $senderCompanyName = $identity->isDomainType() ? $identity->company_name : null;
            $senderDomain = $identity->isDomainType() ? $identity->domain : null;
            $senderEmail = $identity->isEmailType() ? $identity->email : null;
        }

        $result = $this->secretService->createSecret(
            $request->message,
            (int) $request->expires_in,
            $request->user()?->id,
            $maskedRecipientEmail,
            $senderCompanyName,
            $senderDomain,
            $senderEmail,
            $request->file('file'),
            $request->file_original_name,
            $request->hasFile('file') ? (int) $request->file_size : null,
            $request->file_mime_type,
        );

        if ($request->user() && $email = $request->safe()->email) {
            $this->secretService->notifyRecipient($request->user(), $email, $result['url'], $result['secret']->hash_id);
        }

        return back()->with('flash', [
            'secret' => [
                'url' => $result['url'],
                'is_file' => $request->hasFile('file'),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $secret): Response
    {
        $secretRecord = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find(Secret::decodeHashId($secret)));

        $props = [
            'secret' => $secret,
            'decryptUrl' => URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret]),
            'senderCompanyName' => $secretRecord?->sender_company_name,
            'senderDomain' => $secretRecord?->sender_domain,
            'senderEmail' => $secretRecord?->sender_email,
        ];

        if ($secretRecord?->isFileSecret()) {
            $props['isFileSecret'] = true;
            $props['fileSize'] = $secretRecord->file_size;
            $props['fileMimeType'] = $secretRecord->file_mime_type;
            $props['fileDownloadUrl'] = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret]);
            $props['hasMessage'] = $secretRecord->message !== null;
        }

        return Inertia::render('Welcome', $props);
    }

    public function decrypt(Secret $secret): RedirectResponse
    {
        $flash = [];

        if ($secret->isFileSecret()) {
            $flash['is_file'] = true;
            $flash['file_size'] = $secret->file_size;
            $flash['file_mime_type'] = $secret->file_mime_type;
            $flash['file_original_name'] = $secret->filename;
            $flash['file_download_url'] = URL::temporarySignedRoute('secret.file', now()->addMinutes(5), ['secret' => $secret->hash_id]);
            $flash['file_confirm_url'] = URL::temporarySignedRoute('secret.file.downloaded', now()->addMinutes(5), ['secret' => $secret->hash_id]);
        }

        if ($secret->message !== null) {
            $flash['message'] = $secret->message;
            // For combined secrets the retrieved event guard skips nulling the message — do it here.
            if ($secret->isFileSecret()) {
                DB::table($secret->getTable())->where('id', $secret->id)->update(['message' => null]);
            }
        }

        return back()->with('flash', ['secret' => $flash]);
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
    public function confirmFileDownloaded(string $secret): RedirectResponse
    {
        $this->secretService->deleteDownloadedFile($secret);

        return back();
    }

    public function index(Request $request): Response
    {
        $secrets = $this->secretService->listSecrets($request->user());

        $secrets = new SecretResourceCollection($secrets);

        return Inertia::render('Secret/Index', [
            'secrets' => $secrets,
        ]);
    }

    public function destroy(BurnSecretRequest $request, string $secret): void
    {
        $this->secretService->burnSecret($request->getSecretRecord());
    }
}
