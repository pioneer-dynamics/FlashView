<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfigurationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function index(Request $request): Response
    {
        $senderIdentity = $request->user()->senderIdentity;

        return Inertia::render('Settings/Index', [
            'storeMaskedRecipientEmail' => (bool) $request->user()->store_masked_recipient_email,
            'senderIdentity' => $senderIdentity ? [
                'type' => $senderIdentity->type,
                'company_name' => $senderIdentity->company_name,
                'domain' => $senderIdentity->domain,
                'email' => $senderIdentity->email,
                'verification_token' => $senderIdentity->verification_token,
                'is_verified' => $senderIdentity->isVerified(),
            ] : null,
            'planSupportsSenderIdentity' => $request->user()->planSupportsSenderIdentity(),
        ]);
    }

    public function update(UpdateConfigurationRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
