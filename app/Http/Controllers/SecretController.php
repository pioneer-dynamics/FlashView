<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Vinkla\Hashids\Facades\Hashids;
use App\Http\Requests\BurnSecretRequest;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Requests\UpdateSecretRequest;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Resources\SecretResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;

class SecretController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('signed', only: ['show', 'decrypt']),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSecretRequest $request)
    {
        $secret = Secret::create([
            'message' => $request->message,
            'expires_at' => $expires_at = now()->addMinutes((int)$request->expires_in),
            'user_id' => optional($request->user())->id
        ]);

        $url = URL::temporarySignedRoute('secret.show', $expires_at, ['secret' => $secret->hash_id]);

        return back()->with('flash', [
            'secret' => [
                'url' => $url
            ]
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
            ]
        ]);
    }

    public function index(Request $request)
    {
        $secrets = Secret::withoutEvents(fn() => Secret::withoutGlobalScopes()->where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->paginate(2));

        $secrets = new SecretResourceCollection($secrets);

        return Inertia::render('Secret/Index', [
            'secrets' => $secrets
        ]);
    }

    private function getSecretRecordWithoutBurning($secret, $request)
    {
        return Secret::withoutEvents(fn() => Secret::withoutGlobalScopes()->where('user_id', $request->user()->id)->where('id', $this->getIdFromHash($secret))->first());
    }

    public function destroy(BurnSecretRequest $request, $secret)
    {
        $secret = $this->getSecretRecordWithoutBurning($secret, $request);

        $secret->markAsRetrieved();
    }

    private function getIdFromHash($secret)
    {
        return Hashids::connection('Secret')->decode($secret)[0];
    }
}
