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
        $clientType = $request->validated('client_type') ?? 'cli';
        $tokenId = $request->validated('token_id');

        $existingToken = $tokenId
            ? $user->tokens()
                ->where('type', $clientType)
                ->where('id', $tokenId)
                ->first()
            : null;

        $latestToken = $existingToken ?? $user->tokens()
            ->where('type', $clientType)
            ->latest('id')
            ->first();

        $defaultPermissions = $latestToken
            ? $latestToken->abilities
            : Jetstream::$defaultPermissions;

        return Inertia::render('Cli/Authorize', [
            'port' => is_null($request->validated('port')) ? null : (int) $request->validated('port'),
            'state' => $request->validated('state'),
            'name' => $request->validated('name'),
            'redirectUri' => $request->validated('redirect_uri'),
            'clientType' => $clientType,
            'hasApiAccess' => $user->hasApiAccess(),
            'availablePermissions' => Jetstream::$permissions,
            'defaultPermissions' => $defaultPermissions,
            'existingDeviceName' => $existingToken?->name,
        ]);
    }

    /**
     * Generate an authorization code and redirect to CLI callback.
     */
    public function authorize(CliAuthorizeRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $baseCallback = $request->validated('redirect_uri')
            ?? "http://127.0.0.1:{$request->validated('port')}/callback";

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
            'client_type' => $request->validated('client_type') ?? 'cli',
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
        $clientType = $data['client_type'] ?? 'cli';

        $tokenName = $data['name'] ?? $this->generateDefaultInstallationName($user, $clientType);

        $user->tokens()
            ->where('type', $clientType)
            ->where('name', $tokenName)
            ->delete();

        $token = $user->createToken($tokenName, $data['permissions'] ?? Jetstream::$defaultPermissions);
        $token->accessToken->update(['type' => $clientType]);

        return new CliTokenResource([
            'token' => $token->plainTextToken,
            'user' => $user,
            'installation_name' => $tokenName,
        ]);
    }

    private function generateDefaultInstallationName(User $user, string $clientType = 'cli'): string
    {
        $label = $clientType === 'mobile' ? 'Mobile Installation' : 'CLI Installation';
        $count = $user->tokens()->where('type', $clientType)->count() + 1;

        return "{$label} #{$count}";
    }
}
