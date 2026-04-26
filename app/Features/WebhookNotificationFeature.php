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
        return 'Webhook notifcation when a secret is retrieved.';
    }

    public function defaultOrder(): float
    {
        return 7;
    }
}
