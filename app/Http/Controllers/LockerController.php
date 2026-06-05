<?php

namespace App\Http\Controllers;

use App\Http\Requests\Locker\CheckoutLockerRequest;
use App\Http\Requests\Locker\RenewLockerRequest;
use App\Http\Requests\Locker\StoreLockerRequest;
use App\Http\Requests\Locker\UnlockLockerRequest;
use App\Http\Requests\Locker\UpdateLockerRequest;
use App\Http\Requests\Locker\UpgradeAuthLockerRequest;
use App\Models\Locker;
use App\Models\LockerCredit;
use App\Models\LockerPlan;
use App\Services\LockerEcdsaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class LockerController extends Controller
{
    public function __construct(public LockerEcdsaService $ecdsaService) {}

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

        $lockerData = [
            'account_id' => $request->input('account_id'),
            'payload' => $request->input('payload'),
            'storage_path' => $request->input('storage_path'),
            'wrapped_file_key' => $request->input('wrapped_file_key'),
            'expires_at' => now()->addYears($credit->years),
            'auth_mode' => $request->input('auth_mode', 'passphrase'),
            'key_file_count' => $request->input('key_file_count'),
            'show_clues' => $request->boolean('show_clues', true),
        ];

        if ($request->filled('public_key')) {
            $lockerData['public_key'] = $request->input('public_key');
        } else {
            $lockerData['auth_challenge'] = $request->input('auth_challenge');
            $lockerData['auth_verifier'] = $request->input('auth_verifier');
            $lockerData['update_token_hash'] = hash('sha256', $request->input('update_token'));
        }

        $locker = Locker::create($lockerData);

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
        $creditToken = $request->input('credit_token');
        if ($creditToken) {
            $credit = LockerCredit::where('token', $creditToken)->unused()->where('tier', 'file')->first();
            if (! $credit) {
                return response()->json(['error' => 'Invalid or used credit token.'], 422);
            }
        }

        $storagePath = 'lockers/'.Str::uuid().'.bin';

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
    }

    public function authInfo(string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        // Non-existent lockers and lockers with show_clues=false return an opaque
        // passphrase-default response, preventing enumeration of locker existence and auth mode.
        if (! $locker || ! $locker->show_clues) {
            return response()->json(['auth_mode' => 'passphrase', 'key_file_count' => null, 'show_clues' => false]);
        }

        return response()->json([
            'auth_mode' => $locker->auth_mode,
            'key_file_count' => $locker->key_file_count,
            'show_clues' => true,
        ]);
    }

    public function show(Request $request, string $accountId): Response
    {
        return Inertia::render('Locker/Show', [
            'account_id' => $accountId,
            'renewed' => $request->query('renewed') === '1',
        ]);
    }

    public function challenge(Request $request, string $accountId): JsonResponse
    {
        $ip = $request->ip();

        if (RateLimiter::tooManyAttempts('locker-ip:'.$ip, 3)) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour.'], 429);
        }

        $locker = Locker::where('account_id', $accountId)->first();

        if ($locker && $locker->public_key !== null) {
            $data = $this->ecdsaService->issueChallenge($accountId);

            return response()->json($data);
        }

        // Legacy locker or non-existent account — always return a challenge to prevent enumeration.
        $challenge = $locker ? $locker->auth_challenge : bin2hex(random_bytes(32));

        return response()->json(['challenge' => $challenge]);
    }

    public function unlock(UnlockLockerRequest $request, string $accountId): JsonResponse
    {
        $ip = $request->ip();

        if (RateLimiter::tooManyAttempts('locker-ip:'.$ip, 3)) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour.'], 429);
        }

        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker) {
            RateLimiter::hit('locker-ip:'.$ip, 3600);
            usleep(random_int(80_000, 200_000));

            return response()->json(['error' => 'Credentials do not match.'], 401);
        }

        if (RateLimiter::tooManyAttempts('locker-account-lock:'.$accountId, 3)) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour.'], 429);
        }

        if (RateLimiter::tooManyAttempts('locker-account-cooldown:'.$accountId, 1)) {
            return response()->json(['error' => 'Too many attempts. Please wait 5 minutes before trying again.'], 429);
        }

        $verified = false;

        if ($locker->public_key !== null) {
            // ECDSA path
            $challengeHex = $this->ecdsaService->consumeChallenge(
                $request->input('challenge_id'),
                $accountId
            );

            if ($challengeHex !== null) {
                $verified = $this->ecdsaService->verify(
                    $locker->public_key,
                    $challengeHex,
                    $request->input('signature')
                );
            }
        } else {
            // Legacy HMAC path
            $verified = $locker->verifyAuthVerifier($request->input('verifier'));
        }

        if (! $verified) {
            RateLimiter::clear('locker-account-cooldown:'.$accountId);
            RateLimiter::hit('locker-account-cooldown:'.$accountId, 300);
            RateLimiter::hit('locker-account-lock:'.$accountId, 3600);
            if (RateLimiter::tooManyAttempts('locker-account-lock:'.$accountId, 3)) {
                RateLimiter::clear('locker-account-cooldown:'.$accountId);
            }

            return response()->json(['error' => 'Credentials do not match.'], 401);
        }

        if ($locker->isExpired()) {
            return response()->json(['error' => 'This locker has expired and is no longer accessible.'], 410);
        }

        RateLimiter::clear('locker-account-lock:'.$accountId);
        RateLimiter::clear('locker-account-cooldown:'.$accountId);

        $data = [
            'payload' => $locker->payload,
            'is_file_locker' => $locker->isFileLocker(),
            'expires_at' => $locker->expires_at->toIso8601String(),
            'auth_challenge' => $locker->auth_challenge,
        ];

        if ($locker->isFileLocker()) {
            $data['download_url'] = Storage::temporaryUrl($locker->storage_path, now()->addMinutes(15));
            $data['wrapped_file_key'] = $locker->wrapped_file_key;
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
            $data['download_url'] = Storage::temporaryUrl($locker->storage_path, now()->addMinutes(15));
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

        $verified = false;

        if ($locker->public_key !== null) {
            // ECDSA path
            $challengeId = $request->header('X-Signing-Challenge-Id');
            $signature = $request->header('X-Signature');

            if ($challengeId && $signature) {
                $challengeHex = $this->ecdsaService->consumeChallenge($challengeId, $accountId);
                if ($challengeHex !== null) {
                    $verified = $this->ecdsaService->verify($locker->public_key, $challengeHex, $signature);
                }
            }
        } else {
            // Legacy path
            $updateToken = $request->header('X-Update-Token');
            if ($updateToken) {
                $verified = $locker->verifyUpdateToken($updateToken);
            }
        }

        if (! $verified) {
            return response()->json(['error' => 'Invalid credentials.'], 403);
        }

        $updates = [
            'payload' => $request->input('payload'),
        ];

        if ($request->has('storage_path')) {
            $newStoragePath = $request->input('storage_path');
            if ($locker->isFileLocker() && $locker->storage_path && $locker->storage_path !== $newStoragePath) {
                try {
                    Storage::delete($locker->storage_path);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete old locker S3 object', ['path' => $locker->storage_path, 'error' => $e->getMessage()]);
                }
            }
            $updates['storage_path'] = $newStoragePath;
        }

        if ($locker->public_key !== null) {
            // ECDSA passphrase change: update public key when provided
            if ($request->filled('new_public_key')) {
                $updates['public_key'] = $request->input('new_public_key');
            }
        } else {
            // Legacy passphrase change: update verifier and token hash
            if ($request->filled('new_auth_verifier') && $request->filled('new_update_token')) {
                $updates['auth_verifier'] = $request->input('new_auth_verifier');
                $updates['update_token_hash'] = hash('sha256', $request->input('new_update_token'));
            }
        }

        if ($request->filled('new_wrapped_file_key')) {
            $updates['wrapped_file_key'] = $request->input('new_wrapped_file_key');
        }

        $locker->update($updates);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();

        if (! $locker) {
            abort(404);
        }

        $verified = false;

        if ($locker->public_key !== null) {
            $challengeId = $request->header('X-Signing-Challenge-Id');
            $signature = $request->header('X-Signature');

            if ($challengeId && $signature) {
                $challengeHex = $this->ecdsaService->consumeChallenge($challengeId, $accountId);
                if ($challengeHex !== null) {
                    $verified = $this->ecdsaService->verify($locker->public_key, $challengeHex, $signature);
                }
            }
        } else {
            $updateToken = $request->header('X-Update-Token');
            if ($updateToken) {
                $verified = $locker->verifyUpdateToken($updateToken);
            }
        }

        if (! $verified) {
            return response()->json(['error' => 'Invalid credentials.'], 403);
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
            if ($locker->public_key !== null) {
                $data = $this->ecdsaService->issueChallenge($accountId);

                return response()->json($data);
            }

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

        $verified = false;

        if ($locker->public_key !== null) {
            $challengeHex = $this->ecdsaService->consumeChallenge(
                $request->input('challenge_id'),
                $accountId
            );

            if ($challengeHex !== null) {
                $verified = $this->ecdsaService->verify(
                    $locker->public_key,
                    $challengeHex,
                    $request->input('signature')
                );
            }
        } else {
            $verified = $locker->verifyAuthVerifier($request->input('verifier'));
        }

        if (! $verified) {
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

    public function upgradeAuth(UpgradeAuthLockerRequest $request, string $accountId): JsonResponse
    {
        $locker = Locker::where('account_id', $accountId)->first();
        if (! $locker) {
            abort(404);
        }
        if ($locker->isExpired()) {
            abort(410);
        }

        // Idempotent: already upgraded
        if ($locker->public_key !== null) {
            return response()->json(['ok' => true]);
        }

        // Apply the same account-level rate limiter as unlock() — prevents brute-force
        // against the verifier via this endpoint bypassing unlock's protections.
        if (RateLimiter::tooManyAttempts('locker-account-lock:'.$accountId, 3)) {
            return response()->json(['error' => 'Too many failed attempts. Try again in 1 hour. Your locker is unchanged.'], 429);
        }

        if (! $locker->verifyAuthVerifier($request->input('verifier'))) {
            RateLimiter::hit('locker-account-lock:'.$accountId, 3600);

            return response()->json(['error' => 'Invalid passphrase.'], 403);
        }

        RateLimiter::clear('locker-account-lock:'.$accountId);

        $locker->update([
            'public_key' => $request->input('public_key'),
            'auth_challenge' => null,
            'auth_verifier' => null,
            'update_token_hash' => null,
        ]);

        return response()->json(['ok' => true]);
    }
}
