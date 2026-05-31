<?php

namespace App\Http\Controllers;

use App\Http\Requests\Locker\CheckoutLockerRequest;
use App\Http\Requests\Locker\RenewLockerRequest;
use App\Http\Requests\Locker\StoreLockerRequest;
use App\Http\Requests\Locker\UpdateLockerRequest;
use App\Models\Locker;
use App\Models\LockerCredit;
use App\Models\LockerPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class LockerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Locker/Index');
    }

    public function buy(Request $request): Response
    {
        $plans = LockerPlan::where('is_active', true)
            ->orderBy('tier')
            ->orderBy('years')
            ->get()
            ->groupBy('tier')
            ->map(fn ($group) => $group->keyBy('years'));

        return Inertia::render('Locker/Buy', [
            'pricing' => $plans,
        ]);
    }

    public function checkout(CheckoutLockerRequest $request): RedirectResponse|Response|HttpResponse
    {
        $tier = $request->input('tier');
        $years = (int) $request->input('years');

        $plan = LockerPlan::where('tier', $tier)
            ->where('years', $years)
            ->where('is_active', true)
            ->first();

        if (! $plan || ! $plan->stripe_price_id) {
            return redirect()->route('lockers.buy')->with('error', 'Invalid pricing option selected.');
        }

        $priceId = $plan->stripe_price_id;

        try {
            $session = Cashier::stripe()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'metadata' => ['action' => 'create', 'years' => $years, 'tier' => $tier],
                'success_url' => route('lockers.await-credit').'?session={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('lockers.buy'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', ['error' => $e->getMessage()]);

            return redirect()->route('lockers.buy')->with('error', 'Payment service unavailable. Please try again.');
        }

        return Inertia::location($session->url);
    }

    public function awaitCredit(Request $request): Response
    {
        return Inertia::render('Locker/AwaitCredit', [
            'session_id' => $request->query('session'),
        ]);
    }

    public function creditStatus(Request $request): JsonResponse
    {
        $sessionId = $request->query('session');

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session parameter.'], 422);
        }

        $credit = LockerCredit::where('stripe_session_id', $sessionId)->first();

        if (! $credit) {
            return response()->json(['pending' => true]);
        }

        return response()->json(['token' => $credit->token]);
    }

    public function create(Request $request): Response
    {
        $token = $request->query('token');

        $credit = LockerCredit::where('token', $token)->unused()->first();

        if (! $credit) {
            abort(404);
        }

        return Inertia::render('Locker/Create', [
            'credit_token' => $credit->token,
            'tier' => $credit->tier,
            'years' => $credit->years,
        ]);
    }

    public function store(StoreLockerRequest $request): JsonResponse
    {
        $credit = LockerCredit::where('token', $request->input('credit_token'))->unused()->firstOrFail();

        $locker = Locker::create([
            'account_id' => $request->input('account_id'),
            'payload' => $request->input('payload'),
            'storage_path' => $request->input('storage_path'),
            'auth_challenge' => $request->input('auth_challenge'),
            'auth_verifier' => $request->input('auth_verifier'),
            'update_token_hash' => hash('sha256', $request->input('update_token')),
            'expires_at' => now()->addYears($credit->years),
        ]);

        $credit->update([
            'locker_id' => $locker->id,
            'used_at' => now(),
        ]);

        return response()->json([
            'expires_at' => $locker->expires_at->toIso8601String(),
            'account_id' => $locker->account_id,
        ]);
    }

    public function prepareFile(Request $request): JsonResponse
    {
        // If a credit_token is provided, validate it (creation flow).
        // If not (update flow for an existing file locker), skip credit check.
        $creditToken = $request->input('credit_token');
        if ($creditToken) {
            $credit = LockerCredit::where('token', $creditToken)->unused()->where('tier', 'file')->first();
            if (! $credit) {
                return response()->json(['error' => 'Invalid or used credit token.'], 422);
            }
        }

        $storagePath = 'lockers/'.Str::uuid().'.bin';

        try {
            ['url' => $uploadUrl, 'headers' => $uploadHeaders] = Storage::temporaryUploadUrl(
                $storagePath,
                now()->addMinutes(15),
                ['ContentType' => 'application/octet-stream']
            );

            return response()->json([
                'upload_type' => 's3_direct',
                'upload_url' => $uploadUrl,
                'upload_headers' => $uploadHeaders,
                'storage_path' => $storagePath,
            ]);
        } catch (\RuntimeException) {
            $token = Str::uuid()->toString();
            Cache::put("pending_locker_file:{$token}", ['storage_path' => $storagePath], now()->addMinutes(30));

            return response()->json([
                'upload_type' => 'server',
                'upload_url' => URL::temporarySignedRoute('lockers.file.upload', now()->addMinutes(15), ['token' => $token]),
                'upload_headers' => [],
                'storage_path' => $storagePath,
            ]);
        }
    }

    public function handleFileUpload(Request $request, string $token): JsonResponse
    {
        $pending = Cache::get("pending_locker_file:{$token}");

        if (! $pending) {
            abort(404);
        }

        Storage::put($pending['storage_path'], $request->getContent());
        Cache::forget("pending_locker_file:{$token}");

        return response()->json(['ok' => true]);
    }

    public function downloadFile(string $accountId): HttpResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker || $locker->isExpired() || ! $locker->isFileLocker()) {
            abort(404);
        }

        return response(Storage::get($locker->storage_path), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="locker-file.bin"',
        ]);
    }

    public function show(Request $request, string $accountId): Response
    {
        // Always render — never reveal whether an account ID exists.
        // The unlock endpoint is the only place credentials are validated.
        return Inertia::render('Locker/Show', [
            'account_id' => $accountId,
            'renewed' => $request->query('renewed') === '1',
        ]);
    }

    public function challenge(Request $request, string $accountId): JsonResponse
    {
        $ip = $request->ip();

        if (Cache::get("locker:ip:locked:{$ip}")) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour.'], 429);
        }

        $locker = Locker::where('account_id', $accountId)->first();

        // Always return a challenge — fake one for non-existent accounts to prevent enumeration.
        $challenge = $locker ? $locker->auth_challenge : bin2hex(random_bytes(32));

        return response()->json(['challenge' => $challenge]);
    }

    public function unlock(Request $request, string $accountId): JsonResponse
    {
        $request->validate(['verifier' => ['required', 'string', 'size:64']]);

        $ip = $request->ip();
        $verifier = $request->input('verifier');
        $ipFailKey = "locker:ip:fail:{$ip}";
        $ipLockedKey = "locker:ip:locked:{$ip}";

        if (Cache::get($ipLockedKey)) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour.'], 429);
        }

        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker) {
            $count = (int) Cache::get($ipFailKey, 0) + 1;
            Cache::put($ipFailKey, $count, now()->addMinutes(5));
            if ($count >= 3) {
                Cache::put($ipLockedKey, true, now()->addHour());
                Cache::forget($ipFailKey);
            }
            usleep(random_int(80_000, 200_000)); // timing-safe delay

            return response()->json(['error' => 'Credentials do not match.'], 401);
        }

        $failKey = "locker:fail:{$accountId}";
        $lockedKey = "locker:locked:{$accountId}";
        $cooldownKey = "locker:cooldown:{$accountId}";

        if (Cache::get($lockedKey)) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour.'], 429);
        }

        if (Cache::get($cooldownKey)) {
            return response()->json(['error' => 'Too many attempts. Please wait 5 minutes before trying again.'], 429);
        }

        if (! $locker->verifyAuthVerifier($verifier)) {
            $count = (int) Cache::get($failKey, 0) + 1;
            Cache::put($failKey, $count, now()->addHour());
            Cache::put($cooldownKey, true, now()->addMinutes(5));

            if ($count >= 3) {
                Cache::put($lockedKey, true, now()->addHour());
                Cache::forget($failKey);
                Cache::forget($cooldownKey);
            }

            return response()->json(['error' => 'Credentials do not match.'], 401);
        }

        // Correct passphrase but expired — safe to reveal now
        if ($locker->isExpired()) {
            return response()->json(['error' => 'This locker has expired and is no longer accessible.'], 410);
        }

        // Success — clear failure state
        Cache::forget($failKey);
        Cache::forget($cooldownKey);

        $data = [
            'payload' => $locker->payload,
            'is_file_locker' => $locker->isFileLocker(),
            'expires_at' => $locker->expires_at->toIso8601String(),
            'auth_challenge' => $locker->auth_challenge,
        ];

        if ($locker->isFileLocker()) {
            try {
                $data['download_url'] = Storage::temporaryUrl($locker->storage_path, now()->addMinutes(15));
            } catch (\RuntimeException) {
                $data['download_url'] = URL::temporarySignedRoute(
                    'lockers.file.download',
                    now()->addMinutes(15),
                    ['accountId' => $accountId]
                );
            }
        }

        return response()->json($data);
    }

    public function payload(string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker) {
            abort(404);
        }

        if ($locker->isExpired()) {
            abort(410);
        }

        $data = [
            'payload' => $locker->payload,
            'auth_challenge' => $locker->auth_challenge,
        ];

        if ($locker->isFileLocker()) {
            try {
                $data['download_url'] = Storage::temporaryUrl($locker->storage_path, now()->addMinutes(15));
            } catch (\RuntimeException) {
                $data['download_url'] = URL::temporarySignedRoute(
                    'lockers.file.download',
                    now()->addMinutes(15),
                    ['accountId' => $accountId]
                );
            }
        }

        return response()->json($data);
    }

    public function update(UpdateLockerRequest $request, string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker) {
            abort(404);
        }

        if ($locker->isExpired()) {
            abort(410);
        }

        $updateToken = $request->header('X-Update-Token');

        if (! $updateToken || ! $locker->verifyUpdateToken($updateToken)) {
            return response()->json(['error' => 'Invalid update token.'], 403);
        }

        if ($locker->isFileLocker()) {
            $newStoragePath = $request->input('storage_path');
            if ($locker->storage_path && $locker->storage_path !== $newStoragePath) {
                try {
                    Storage::delete($locker->storage_path);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete old locker S3 object', ['path' => $locker->storage_path, 'error' => $e->getMessage()]);
                }
            }
        }

        $locker->update([
            'payload' => $request->input('payload'),
            'storage_path' => $request->input('storage_path'),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker) {
            abort(404);
        }

        $updateToken = $request->header('X-Update-Token');

        if (! $updateToken || ! $locker->verifyUpdateToken($updateToken)) {
            return response()->json(['error' => 'Invalid update token.'], 403);
        }

        if ($locker->isFileLocker()) {
            try {
                Storage::delete($locker->storage_path);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete locker S3 object on destroy', ['path' => $locker->storage_path, 'error' => $e->getMessage()]);
            }
        }

        $locker->delete();

        return response()->json(['ok' => true]);
    }

    public function renewChallenge(Request $request, string $accountId): JsonResponse|Response
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker || $locker->isExpired()) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['challenge' => $locker->auth_challenge]);
        }

        return Inertia::render('Locker/Renew', [
            'account_id' => $locker->account_id,
            'tier' => $locker->isFileLocker() ? 'file' : 'text',
            'expires_at' => $locker->expires_at->toIso8601String(),
        ]);
    }

    public function renewPurchase(RenewLockerRequest $request, string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker || $locker->isExpired()) {
            return response()->json(['error' => 'Locker not found.'], 404);
        }

        if (! $locker->verifyAuthVerifier($request->input('verifier'))) {
            return response()->json(['error' => 'Invalid passphrase.'], 403);
        }

        $tier = $request->input('tier');
        $years = (int) $request->input('years');

        $plan = LockerPlan::where('tier', $tier)
            ->where('years', $years)
            ->where('is_active', true)
            ->first();

        if (! $plan || ! $plan->stripe_price_id) {
            return response()->json(['error' => 'Invalid pricing option.'], 422);
        }

        $priceId = $plan->stripe_price_id;

        try {
            $session = Cashier::stripe()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'metadata' => [
                    'action' => 'renewal',
                    'account_id' => $accountId,
                    'years' => $years,
                    'tier' => $tier,
                ],
                'success_url' => route('lockers.show', $accountId).'?renewed=1',
                'cancel_url' => route('lockers.show', $accountId),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe renewal checkout session creation failed', ['error' => $e->getMessage(), 'account_id' => $accountId]);

            return response()->json(['error' => 'Payment service unavailable. Please try again.'], 503);
        }

        return response()->json(['checkout_url' => $session->url]);
    }
}
