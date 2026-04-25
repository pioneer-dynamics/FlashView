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
        return 'Up to :expiry_minutes expiry';
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
        ];
    }

    public function resolveLabel(array $config): string
    {
        return 'Up to '.$this->minutesToHuman((int) ($config['expiry_minutes'] ?? 0)).' expiry';
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return $value <= ($config['expiry_minutes'] ?? PHP_INT_MAX);
    }

    private function minutesToHuman(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 minutes';
        }
        if ($minutes % 10080 === 0) {
            $n = intdiv($minutes, 10080);

            return $n === 1 ? '1 week' : "{$n} weeks";
        }
        if ($minutes % 1440 === 0) {
            $n = intdiv($minutes, 1440);

            return $n === 1 ? '1 day' : "{$n} days";
        }
        if ($minutes % 60 === 0) {
            $n = intdiv($minutes, 60);

            return $n === 1 ? '1 hour' : "{$n} hours";
        }

        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
