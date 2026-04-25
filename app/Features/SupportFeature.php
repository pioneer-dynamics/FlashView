<?php

namespace App\Features;

class SupportFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'support';
    }

    public function label(): string
    {
        return 'Priority support';
    }

    public function description(): string
    {
        return 'Provides access to priority support channels.';
    }

    public function defaultOrder(): float
    {
        return 8;
    }
}
