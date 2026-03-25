<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'webhook_url' => $this->webhook_url,
            'webhook_secret' => $this->maskedWebhookSecret(),
            'configured' => $this->hasWebhookConfigured(),
        ];
    }

    private function maskedWebhookSecret(): ?string
    {
        if (blank($this->webhook_secret)) {
            return null;
        }

        return str_repeat('*', 56).substr($this->webhook_secret, -8);
    }
}
