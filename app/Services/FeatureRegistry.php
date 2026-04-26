<?php

namespace App\Services;

use App\Features\Contracts\PlanFeature;
use RuntimeException;

class FeatureRegistry
{
    /** @param PlanFeature[] $features */
    public function __construct(private readonly array $features) {}

    /** @return PlanFeature[] */
    public function all(): array
    {
        return $this->features;
    }

    public function has(string $key): bool
    {
        return collect($this->features)->contains(fn (PlanFeature $f) => $f->key() === $key);
    }

    public function get(string $key): PlanFeature
    {
        $feature = collect($this->features)->first(fn (PlanFeature $f) => $f->key() === $key);

        if ($feature === null) {
            throw new RuntimeException("Unknown plan feature key: [{$key}]");
        }

        return $feature;
    }

    /** @return array<int, array{key: string, label: string, description: string, defaultOrder: float, canBeLimit: bool, configSchema: array}> */
    public function forFrontend(): array
    {
        return collect($this->features)->map(fn (PlanFeature $f) => [
            'key' => $f->key(),
            'label' => $f->label(),
            'description' => $f->description(),
            'defaultOrder' => $f->defaultOrder(),
            'canBeLimit' => $f->canBeLimit(),
            'configSchema' => $f->configSchema(),
        ])->values()->all();
    }
}
