<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSenderIdentityRequest;
use App\Jobs\RetryDomainVerification;
use App\Notifications\DomainVerifiedNotification;
use App\Services\DomainVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SenderIdentityController extends Controller
{
    public function __construct(private DomainVerificationService $verificationService) {}

    public function store(StoreSenderIdentityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $identity = $user->senderIdentity;
        $isEmailType = $request->input('type') === 'email';

        if ($isEmailType) {
            $data = [
                'type' => 'email',
                'company_name' => null,
                'domain' => null,
                'email' => $user->email,
                'verification_token' => null,
                'verified_at' => now(),
                'verification_retry_dispatched_at' => null, // cancel any in-flight domain retry
            ];

            if (! $identity) {
                $user->senderIdentity()->create($data);
            } else {
                $identity->update($data);
            }

            return back();
        }

        $domainChanged = $identity && $identity->domain !== $request->input('domain');

        $data = [
            'type' => 'domain',
            'company_name' => $request->input('company_name'),
            'domain' => $request->input('domain'),
            'email' => null,
        ];

        if (! $identity) {
            $data['verification_token'] = $this->verificationService->generateToken();
            $user->senderIdentity()->create($data);
        } else {
            if ($domainChanged || $identity->isEmailType()) {
                $data['verification_token'] = $this->verificationService->generateToken();
                $data['verified_at'] = null;
                $data['verification_retry_dispatched_at'] = null;
            }
            $identity->update($data);
        }

        return back();
    }

    public function verify(Request $request): RedirectResponse
    {
        $identity = $request->user()->senderIdentity;

        if (! $identity || ! $identity->isDomainType()) {
            return back()->withErrors(['domain' => 'No domain identity configured.']);
        }

        if ($this->verificationService->verify($identity)) {
            $identity->update([
                'verified_at' => now(),
                'verification_retry_dispatched_at' => null,
            ]);
            $request->user()->notify(new DomainVerifiedNotification($identity));

            return back()->with('status', 'domain-verified');
        }

        if ($identity->hasActiveRetry()) {
            return back()->withErrors(['domain' => "We're already working on verifying your domain in the background. You'll receive an email once it's done."]);
        }

        $identity->update(['verification_retry_dispatched_at' => now()]);
        RetryDomainVerification::dispatch($identity, $identity->verification_token, now()->addHours(24));

        return back()->withErrors(['domain' => 'DNS TXT record not found. Please check the record is published and try again in a few minutes.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->senderIdentity?->delete();

        return back();
    }
}
