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

    public function resolveLabel(array $config): string
    {
        $formatted = collect($config)->map(fn ($v) => is_numeric($v) ? number_format((float) $v) : $v)->all();

        return __($this->label(), $formatted);
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return true;
    }
}
