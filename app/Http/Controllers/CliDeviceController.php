<?php

namespace App\Http\Controllers;

use App\Http\Requests\CliDeviceActivateRequest;
use App\Http\Requests\CliDeviceInitiateRequest;
use App\Http\Requests\CliDevicePollRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CliDeviceController extends Controller
{
    private const TTL_SECONDS = 900;

    /**
     * Initiate a device code session and return codes for the CLI to display.
     */
    public function initiate(CliDeviceInitiateRequest $request): JsonResponse
    {
        $deviceCode = Str::random(64);
        $userCode = $this->generateUserCode();
        $name = $request->validated('name') ?? 'CLI Device';
        $tokenId = $request->validated('token_id');

        $payload = [
            'user_code' => $userCode,
            'status' => 'pending',
            'name' => $name,
            'token_id' => $tokenId,
        ];

        Cache::put("cli_device:{$deviceCode}", $payload, now()->addSeconds(self::TTL_SECONDS));
        Cache::put("cli_device:user:{$userCode}", $deviceCode, now()->addSeconds(self::TTL_SECONDS));

        $deviceUrl = route('cli.device');
        if ($tokenId) {
            $deviceUrl .= '?token_id='.$tokenId;
        }

        return response()->json([
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'device_url' => $deviceUrl,
            'expires_in' => self::TTL_SECONDS,
        ]);
    }

    /**
     * Render the device code entry page for authenticated users.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        $existingToken = $request->query('token_id')
            ? $user->tokens()->where('type', 'cli')->where('id', $request->query('token_id'))->first()
            : null;

        $latestCliToken = $existingToken ?? $user->tokens()->where('type', 'cli')->latest('id')->first();

        $defaultPermissions = $latestCliToken
            ? $latestCliToken->abilities
            : Jetstream::$defaultPermissions;

        return Inertia::render('Cli/Device', [
            'hasApiAccess' => $user->hasApiAccess(),
            'availablePermissions' => Jetstream::$permissions,
            'defaultPermissions' => $defaultPermissions,
            'existingDeviceName' => $existingToken?->name,
        ]);
    }

    /**
     * Activate a device code: create a CLI token and redirect with success flash.
     */
    public function activate(CliDeviceActivateRequest $request): SymfonyResponse
    {
        $userCode = strtoupper($request->validated('user_code'));
        $name = $request->validated('name');

        $deviceCode = Cache::get("cli_device:user:{$userCode}");

        if (! $deviceCode) {
            return back()->withErrors(['user_code' => 'Invalid or expired code. Please check the code and try again.']);
        }

        $data = Cache::get("cli_device:{$deviceCode}");

        if (! $data || $data['status'] !== 'pending') {
            Cache::forget("cli_device:user:{$userCode}");

            return back()->withErrors(['user_code' => 'This code has already been used or has expired.']);
        }

        $user = $request->user();

        if (! $user->hasApiAccess()) {
            Cache::put("cli_device:{$deviceCode}", array_merge($data, [
                'status' => 'denied',
                'reason' => 'no_api_access',
            ]), now()->addSeconds(self::TTL_SECONDS));
            Cache::forget("cli_device:user:{$userCode}");

            return back()->withErrors(['user_code' => 'Your plan does not include API access. Please upgrade to use the CLI.']);
        }

        $existingToken = isset($data['token_id'])
            ? $user->tokens()->where('type', 'cli')->where('id', $data['token_id'])->first()
            : null;

        $installationName = $name
            ?: $existingToken?->name
            ?: ($data['name'] ?? $this->generateDefaultInstallationName($user));

        $defaultPermissions = $existingToken?->abilities ?? Jetstream::$defaultPermissions;

        $permissions = array_values(array_intersect(
            $request->validated('permissions') ?? $defaultPermissions,
            Jetstream::$permissions,
        ));

        $user->tokens()
            ->where('type', 'cli')
            ->where('name', $installationName)
            ->delete();

        $token = $user->createToken($installationName, $permissions ?: Jetstream::$defaultPermissions);
        $token->accessToken->update(['type' => 'cli']);

        Cache::put("cli_device:{$deviceCode}", [
            'status' => 'authorized',
            'token' => $token->plainTextToken,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'installation_name' => $installationName,
        ], now()->addSeconds(self::TTL_SECONDS));

        Cache::forget("cli_device:user:{$userCode}");

        // PRG: redirect to GET /cli/device with flash to avoid re-submission on browser refresh
        return redirect()->route('cli.device')->with('success', true);
    }

    /**
     * Poll for device code status; returns pending/authorized/denied/expired as JSON.
     */
    public function poll(CliDevicePollRequest $request): JsonResponse
    {
        $deviceCode = $request->validated('device_code');
        $data = Cache::get("cli_device:{$deviceCode}");

        if (! $data) {
            return response()->json(['status' => 'expired'], 401);
        }

        if ($data['status'] === 'authorized') {
            // Delete immediately — token must not remain in cache after being retrieved
            Cache::forget("cli_device:{$deviceCode}");

            return response()->json([
                'status' => 'authorized',
                'token' => $data['token'],
                'user' => [
                    'name' => $data['user_name'],
                    'email' => $data['user_email'],
                ],
                'installation_name' => $data['installation_name'],
            ]);
        }

        return match ($data['status']) {
            'pending' => response()->json(['status' => 'pending'], 202),
            'denied' => response()->json([
                'status' => 'denied',
                'reason' => $data['reason'],
            ], 403),
            default => response()->json(['status' => 'expired'], 401),
        };
    }

    private function generateUserCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude ambiguous chars (0/O, 1/I)
        $part1 = '';
        $part2 = '';
        for ($i = 0; $i < 4; $i++) {
            $part1 .= $chars[random_int(0, strlen($chars) - 1)];
            $part2 .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return "{$part1}-{$part2}";
    }

    private function generateDefaultInstallationName(User $user): string
    {
        $count = $user->tokens()->where('type', 'cli')->count() + 1;

        return "CLI Installation #{$count}";
    }
}
