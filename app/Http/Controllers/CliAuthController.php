<?php

namespace App\Http\Controllers;

use App\Http\Requests\CliAuthorizeRequest;
use App\Http\Requests\CliTokenExchangeRequest;
use App\Http\Resources\CliTokenResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class CliAuthController extends Controller
{
    /**
     * Show the CLI authorization page.
     */
    public function show(CliAuthorizeRequest $request): Response
    {
        return Inertia::render('Cli/Authorize', [
            'port' => (int) $request->validated('port'),
            'state' => $request->validated('state'),
            'hasApiAccess' => $request->user()->hasApiAccess(),
            'defaultPermissions' => Jetstream::$defaultPermissions,
        ]);
    }

    /**
     * Generate an authorization code and redirect to CLI callback.
     */
    public function authorize(CliAuthorizeRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $baseCallback = "http://127.0.0.1:{$request->validated('port')}/callback";

        if ($request->input('action') === 'deny') {
            return Inertia::location($baseCallback.'?'.http_build_query([
                'error' => 'denied',
                'state' => $request->validated('state'),
            ]));
        }

        $user = $request->user();

        if (! $user->hasApiAccess()) {
            return Inertia::location($baseCallback.'?'.http_build_query([
                'error' => 'no_api_access',
                'state' => $request->validated('state'),
            ]));
        }

        $code = Str::random(64);

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $request->validated('state'),
        ], now()->addSeconds(60));

        return Inertia::location($baseCallback.'?'.http_build_query([
            'code' => $code,
            'state' => $request->validated('state'),
        ]));
    }

    /**
     * Exchange an authorization code for a Sanctum API token.
     */
    public function exchangeToken(CliTokenExchangeRequest $request): CliTokenResource|JsonResponse
    {
        $cacheKey = "cli_auth:{$request->validated('code')}";
        $data = Cache::pull($cacheKey);

        if (! $data) {
            return response()->json([
                'message' => 'Invalid or expired authorization code.',
            ], 401);
        }

        if ($data['state'] !== $request->validated('state')) {
            return response()->json([
                'message' => 'State parameter mismatch.',
            ], 422);
        }

        $user = User::findOrFail($data['user_id']);

        $token = $user->createToken('FlashView CLI', Jetstream::$defaultPermissions);

        return new CliTokenResource([
            'token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }
}
