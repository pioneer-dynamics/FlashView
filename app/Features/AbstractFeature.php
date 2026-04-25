<?php

namespace App\Features;

use App\Features\Contracts\PlanFeature;

abstract class AbstractFeature implements PlanFeature
{
    public function canBeLimit(): bool
    {
        return false;
    }

    public function configSchema(): array
    {
        return [];
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return true;
    }
}
