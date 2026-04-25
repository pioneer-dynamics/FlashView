<?php

namespace App\Features;

class MessagesFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'messages';
    }

    public function label(): string
    {
        return ':message_length character limit per message';
    }

    public function description(): string
    {
        return 'Caps the maximum size of each secret message.';
    }

    public function defaultOrder(): float
    {
        return 2;
    }

    public function canBeLimit(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'message_length', 'type' => 'number', 'label' => 'Character Limit', 'default' => 100000, 'min' => 1],
        ];
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return $value <= ($config['message_length'] ?? PHP_INT_MAX);
    }
}
