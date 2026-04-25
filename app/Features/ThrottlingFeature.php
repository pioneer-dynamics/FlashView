<?php

namespace App\Features;

class ThrottlingFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'throttling';
    }

    public function label(): string
    {
        return ':per_minute requests per minute';
    }

    public function description(): string
    {
        return 'Limits the number of secrets that can be created per minute.';
    }

    public function defaultOrder(): float
    {
        return 4;
    }

    public function canBeLimit(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'per_minute', 'type' => 'number', 'label' => 'Requests Per Minute', 'default' => 60, 'min' => 1],
        ];
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return $value <= ($config['per_minute'] ?? PHP_INT_MAX);
    }
}
