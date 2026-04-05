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
        return Inertia::render('Settings/Index', [
            'storeMaskedRecipientEmail' => (bool) $request->user()->store_masked_recipient_email,
        ]);
    }

    public function update(UpdateConfigurationRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
