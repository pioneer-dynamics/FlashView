<?php

namespace App\Features;

class WebhookNotificationFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'webhook_notification';
    }

    public function label(): string
    {
        return 'Webhook notifications';
    }

    public function description(): string
    {
        return 'Posts a callback to a webhook URL when a secret is viewed.';
    }

    public function defaultOrder(): float
    {
        return 7;
    }
}
