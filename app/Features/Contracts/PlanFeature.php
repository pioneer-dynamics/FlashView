<?php

namespace App\Features\Contracts;

interface PlanFeature
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    public function defaultOrder(): float;

    public function canBeLimit(): bool;

    /** @return array<int, array{key: string, type: string, label: string, default: mixed, min?: int}> */
    public function configSchema(): array;

    public function resolveLabel(array $config): string;

    public function withinLimit(mixed $value, array $config): bool;
}
