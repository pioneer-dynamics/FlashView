<?php

namespace App\Features;

class EmailNotificationFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'email_notification';
    }

    public function label(): string
    {
        return 'Email notifications';
    }

    public function description(): string
    {
        return 'Sends an email when a secret is viewed.';
    }

    public function defaultOrder(): float
    {
        return 6;
    }
}
