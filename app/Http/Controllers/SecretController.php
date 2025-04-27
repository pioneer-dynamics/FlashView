<?php

namespace App\Http\Controllers;

use App\Http\Requests\BurnSecretRequest;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Resources\SecretResourceCollection;
use App\Mail\NewSecretNotification;
use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

class SecretController extends Controller implements HasMiddleware
{
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
        $secret = Secret::create([
            'message' => $request->message,
            'expires_at' => $expires_at = now()->addMinutes((int) $request->expires_in),
            'user_id' => optional($request->user())->id,
        ]);

        $url = URL::temporarySignedRoute('secret.show', $expires_at, ['secret' => $secret->hash_id]);

        if ($request->user()) {
            if ($email = $request->safe()->email) {
                Mail::to($email)->send(new NewSecretNotification($request->user(), $url, $secret->hash_id));
            }
        }

        return back()->with('flash', [
            'secret' => [
                'url' => $url,
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
        $secrets = Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->paginate());

        $secrets = new SecretResourceCollection($secrets);

        return Inertia::render('Secret/Index', [
            'secrets' => $secrets,
        ]);
    }

    private function getSecretRecordWithoutBurning($secret, $request)
    {
        return Secret::withoutEvents(fn () => Secret::withoutGlobalScopes()->where('user_id', $request->user()->id)->where('id', $this->getIdFromHash($secret))->first());
    }

    public function destroy(BurnSecretRequest $request, $secret)
    {
        $secret = $this->getSecretRecordWithoutBurning($secret, $request);

        $secret->markSilentlyAsRetrieved();
    }

    private function getIdFromHash($secret)
    {
        return Hashids::connection('Secret')->decode($secret)[0];
    }
}
