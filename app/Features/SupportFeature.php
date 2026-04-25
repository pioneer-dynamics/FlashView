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
        return ':support_type Support';
    }

    public function description(): string
    {
        return 'Support access';
    }

    public function defaultOrder(): float
    {
        return 8;
    }

    public function canBeLimit(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            [
                'key' => 'support_type',
                'type' => 'select',
                'label' => 'Support Tier',
                'options' => [
                    ['value' => 'standard', 'label' => 'Standard'],
                    ['value' => 'priority', 'label' => 'Priority'],
                ],
                'default' => 'standard',
            ],
        ];
    }

    public function resolveLabel(array $config): string
    {
        $tier = $config['support_type'] ?? 'standard';

        return ucfirst($tier).' Support';
    }
}
