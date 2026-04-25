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
        return 'Custom sender identity';
    }

    public function description(): string
    {
        return 'Shows the sender\'s name or brand on shared secrets.';
    }

    public function defaultOrder(): float
    {
        return 10;
    }
}
