<?php

namespace App\Http\Controllers;

use App\Http\Requests\BurnSecretRequest;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Resources\SecretResourceCollection;
use App\Models\Secret;
use App\Services\SecretService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

class SecretController extends Controller implements HasMiddleware
{
    public function __construct(private SecretService $secretService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('signed', only: ['show', 'decrypt']),
        ];
    }

    public function report($secret)
    {
        $secret = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->find($this->getIdFromHash($secret)));

        // collect details from recipient as proof of spam or abuse.
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSecretRequest $request)
    {
        $result = $this->secretService->createSecret(
            $request->message,
            (int) $request->expires_in,
            $request->user()?->id,
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
    public function show($secret)
    {
        return Inertia::render('Welcome', ['secret' => $secret, 'decryptUrl' => URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret])]);
    }

    public function decrypt(Secret $secret)
    {
        return back()->with('flash', [
            'secret' => [
                'message' => $secret->message,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $secrets = $this->secretService->listSecrets($request->user());

        $secrets = new SecretResourceCollection($secrets);

        return Inertia::render('Secret/Index', [
            'secrets' => $secrets,
        ]);
    }

    public function destroy(BurnSecretRequest $request, $secret)
    {
        $this->secretService->burnSecret($request->getSecretRecord());
    }

    private function getIdFromHash($secret)
    {
        return Hashids::connection('Secret')->decode($secret)[0];
    }
}
