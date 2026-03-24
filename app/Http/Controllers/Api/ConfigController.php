<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfigRequest;
use App\Http\Resources\ConfigResource;

class ConfigController extends Controller
{
    public function __invoke(ConfigRequest $request): ConfigResource
    {
        $data = [
            'expiry_options' => config('secrets.expiry_options'),
            'expiry_limits' => config('secrets.expiry_limits'),
            'message_length' => config('secrets.message_length'),
        ];

        $user = $request->user();
        if ($user->subscribed()) {
            $plan = $user->resolvePlan();
            if ($plan) {
                $data['plan_limits'] = [
                    'expiry_minutes' => $plan->features['expiry']['config']['expiry_minutes'] ?? null,
                    'message_length' => $plan->features['messages']['config']['message_length'] ?? null,
                ];
            }
        }

        return new ConfigResource($data);
    }
}
