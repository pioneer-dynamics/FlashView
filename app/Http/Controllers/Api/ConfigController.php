<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfigRequest;
use App\Http\Resources\ConfigResource;

class ConfigController extends Controller
{
    /**
     * Return the authenticated user's configuration limits.
     */
    public function __invoke(ConfigRequest $request): ConfigResource
    {
        $user = $request->user();
        $plan = $user->resolvePlan();

        $maxExpiry = $plan
            ? ($plan->features['expiry']['config']['expiry_minutes'] ?? config('secrets.expiry_limits.user'))
            : config('secrets.expiry_limits.user');

        $maxMessageLength = $plan
            ? ($plan->features['messages']['config']['message_length'] ?? config('secrets.message_length.user'))
            : config('secrets.message_length.user');

        $expiryOptions = array_values(array_filter(
            config('secrets.expiry_options'),
            fn (array $option) => $option['value'] <= $maxExpiry,
        ));

        $senderIdentity = null;
        if ($user->hasVerifiedSenderIdentity()) {
            $identity = $user->senderIdentity;
            $senderIdentity = [
                'type' => $identity->type,
                'company_name' => $identity->company_name,
                'email' => $identity->email,
                'include_by_default' => $identity->include_by_default,
            ];
        }

        return new ConfigResource([
            'expiry_options' => $expiryOptions,
            'max_expiry' => $maxExpiry,
            'max_message_length' => $maxMessageLength,
            'sender_identity' => $senderIdentity,
        ]);
    }
}
