<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebhookSettingsRequest;
use App\Http\Resources\WebhookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function show(Request $request): WebhookResource
    {
        abort_unless($request->user()->planSupportsWebhook(), 403);

        return new WebhookResource($request->user());
    }

    public function update(UpdateWebhookSettingsRequest $request): JsonResponse
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

        return response()->json(array_merge(
            (new WebhookResource($user))->resolve(),
            ['message' => 'Webhook settings updated.'],
        ));
    }

    public function regenerateSecret(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->planSupportsWebhook(), 403);
        abort_unless($user->hasWebhookConfigured(), 422);

        $newSecret = bin2hex(random_bytes(32));
        $user->updateQuietly(['webhook_secret' => $newSecret]);

        return response()->json([
            'webhook_secret' => $newSecret,
            'message' => 'Webhook secret regenerated. Update your integration with the new secret.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_unless($request->user()->planSupportsWebhook(), 403);
        $request->user()->updateQuietly([
            'webhook_url' => null,
            'webhook_secret' => null,
        ]);

        return response()->json([
            'message' => 'Webhook configuration removed.',
        ]);
    }
}
