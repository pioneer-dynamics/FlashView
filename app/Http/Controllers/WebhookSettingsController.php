<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWebhookSettingsRequest;
use App\Jobs\SendWebhookNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function revealSecret(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->planSupportsWebhook(), 403);
        abort_unless($user->hasWebhookConfigured(), 422);

        return back()->with('flash', [
            'webhookSecret' => $user->webhook_secret,
        ]);
    }

    public function regenerateSecret(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->planSupportsWebhook(), 403);
        abort_unless($user->hasWebhookConfigured(), 422);

        $user->updateQuietly([
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        return back()->with('flash', [
            'webhookSecret' => $user->fresh()->webhook_secret,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->planSupportsWebhook(), 403);

        $user->updateQuietly([
            'webhook_url' => null,
            'webhook_secret' => null,
        ]);

        return back();
    }

    public function test(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->planSupportsWebhook(), 403);
        abort_unless($user->hasWebhookConfigured(), 422);

        SendWebhookNotification::dispatch(
            webhookUrl: $user->webhook_url,
            webhookSecret: $user->webhook_secret,
            hashId: 'test-'.Str::random(5),
            createdAt: now()->toIso8601String(),
            retrievedAt: now()->toIso8601String(),
            userId: $user->id,
            event: 'ping',
        );

        return back();
    }
}
