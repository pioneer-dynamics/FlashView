<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWebhookSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebhookSettingsController extends Controller
{
    public function update(UpdateWebhookSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (filled($data['webhook_url']) && blank($user->webhook_secret)) {
            $data['webhook_secret'] = bin2hex(random_bytes(32));
        }

        if (blank($data['webhook_url'])) {
            $data['webhook_secret'] = null;
        }

        $user->updateQuietly($data);

        return back();
    }

    public function regenerateSecret(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasWebhookConfigured(), 422);

        $user->updateQuietly([
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        return back();
    }
}
