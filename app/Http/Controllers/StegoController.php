<?php

namespace App\Http\Controllers;

use App\Http\Requests\StegoPageRequest;
use App\Http\Requests\StegoSignRequest;
use App\Http\Requests\StegoVerifyRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class StegoController extends Controller
{
    public function index(StegoPageRequest $request): Response
    {
        return Inertia::render('Secret/StegoPage', [
            'canUseStego' => $request->canEmbed(),
        ]);
    }

    /**
     * Sign the stego payload with the authenticated user's verified sender identity.
     *
     * Mirrors the double-gate from SecretController: a downgraded user retains their identity
     * record, so checking only hasVerifiedSenderIdentity() is insufficient — the plan must also
     * support sender identity.
     */
    public function sign(StegoSignRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->planSupportsSenderIdentity() || ! $user->hasVerifiedSenderIdentity()) {
            return response()->json(['message' => 'No verified sender identity.'], 403);
        }

        $identity = $user->senderIdentity;

        $verifiedIdentity = [
            'company_name' => $identity->company_name,
            'domain' => $identity->domain,
            'email' => $identity->email,
            'type' => $identity->type,
        ];
        ksort($verifiedIdentity);

        $payload = [
            'ciphertext' => $request->ciphertext,
            'verified_identity' => $verifiedIdentity,
        ];
        ksort($payload);

        $canonical = json_encode($payload);
        $signature = hash_hmac('sha256', $canonical, config('app.key'));

        return response()->json([
            'signature' => $signature,
            'verified_identity' => $verifiedIdentity,
        ]);
    }

    public function verify(StegoVerifyRequest $request): JsonResponse
    {
        $verifiedIdentity = $request->verified_identity;
        ksort($verifiedIdentity);

        $payload = [
            'ciphertext' => $request->ciphertext,
            'verified_identity' => $verifiedIdentity,
        ];
        ksort($payload);

        $canonical = json_encode($payload);
        $expected = hash_hmac('sha256', $canonical, config('app.key'));
        $verified = hash_equals($expected, $request->signature);

        return response()->json(['verified' => $verified]);
    }
}
