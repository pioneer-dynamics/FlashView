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
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class SecretController extends Controller implements HasMiddleware
{
    public function __construct(
        private SecretService $secretService,
        private EmailMaskingService $emailMaskingService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('signed', only: ['show', 'decrypt']),
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

        if ($request->user()?->store_masked_recipient_email && $email = $request->safe()->email) {
            $maskedRecipientEmail = $this->emailMaskingService->mask($email);
        }

        $result = $this->secretService->createSecret(
            $request->message,
            (int) $request->expires_in,
            $request->user()?->id,
            $maskedRecipientEmail,
        );

        if ($request->user() && $email = $request->safe()->email) {
            $this->secretService->notifyRecipient($request->user(), $email, $result['url'], $result['secret']->hash_id);
        }

        return back()->with('flash', [
            'secret' => [
                'url' => $result['url'],
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $secret): Response
    {
        return Inertia::render('Welcome', ['secret' => $secret, 'decryptUrl' => URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret])]);
    }

    public function decrypt(Secret $secret): RedirectResponse
    {
        return back()->with('flash', [
            'secret' => [
                'message' => $secret->message,
            ],
        ]);
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
