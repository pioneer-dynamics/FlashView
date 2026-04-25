<?php

namespace App\Features;

class ApiFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'api';
    }

    public function label(): string
    {
        return 'API access';
    }

    public function description(): string
    {
        return 'API and CLI Access';
    }

    public function defaultOrder(): float
    {
        return 9;
    }
}
