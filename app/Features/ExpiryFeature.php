<?php

namespace App\Features;

class ExpiryFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'expiry';
    }

    public function label(): string
    {
        return 'Up to :expiry_label expiry';
    }

    public function description(): string
    {
        return 'Sets the maximum allowed expiry duration for secrets.';
    }

    public function defaultOrder(): float
    {
        return 3;
    }

    public function canBeLimit(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'expiry_minutes', 'type' => 'number', 'label' => 'Max Expiry (minutes)', 'default' => 43200, 'min' => 1],
            ['key' => 'expiry_label', 'type' => 'text', 'label' => 'Expiry Label', 'default' => '30 days'],
        ];
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return $value <= ($config['expiry_minutes'] ?? PHP_INT_MAX);
    }
}
