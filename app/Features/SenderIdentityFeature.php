<?php

namespace App\Features;

class SenderIdentityFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'sender_identity';
    }

    public function label(): string
    {
        return 'Verified sender identity';
    }

    public function description(): string
    {
        return 'Shows a verified badge to messages sent (optional)';
    }

    public function defaultOrder(): float
    {
        return 10;
    }
}
