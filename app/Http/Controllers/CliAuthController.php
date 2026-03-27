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
        $user = $request->user();

        $latestCliToken = $user->tokens()
            ->where('type', 'cli')
            ->latest('id')
            ->first();

        $defaultPermissions = $latestCliToken
            ? $latestCliToken->abilities
            : Jetstream::$defaultPermissions;

        return Inertia::render('Cli/Authorize', [
            'port' => (int) $request->validated('port'),
            'state' => $request->validated('state'),
            'name' => $request->validated('name'),
            'hasApiAccess' => $user->hasApiAccess(),
            'availablePermissions' => Jetstream::$permissions,
            'defaultPermissions' => $defaultPermissions,
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

        $permissions = array_values(array_intersect(
            $request->input('permissions', Jetstream::$defaultPermissions),
            Jetstream::$permissions,
        ));

        $code = Str::random(64);

        Cache::put("cli_auth:{$code}", [
            'user_id' => $user->id,
            'state' => $request->validated('state'),
            'permissions' => $permissions,
            'name' => $request->validated('name'),
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

        $tokenName = $data['name'] ?? $this->generateDefaultInstallationName($user);

        $user->tokens()
            ->where('type', 'cli')
            ->where('name', $tokenName)
            ->delete();

        $token = $user->createToken($tokenName, $data['permissions'] ?? Jetstream::$defaultPermissions);
        $token->accessToken->update(['type' => 'cli']);

        return new CliTokenResource([
            'token' => $token->plainTextToken,
            'user' => $user,
            'installation_name' => $tokenName,
        ]);
    }

    private function generateDefaultInstallationName(User $user): string
    {
        $count = $user->tokens()->where('type', 'cli')->count() + 1;

        return "CLI Installation #{$count}";
    }
}
