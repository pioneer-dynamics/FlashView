<?php

namespace App\Features;

class FileUploadFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'file_upload';
    }

    public function label(): string
    {
        return 'Up to :max_file_size_mb MB file uploads';
    }

    public function description(): string
    {
        return 'Allows file attachments up to a configurable size limit.';
    }

    public function defaultOrder(): float
    {
        return 5;
    }

    public function canBeLimit(): bool
    {
        return true;
    }

    public function configSchema(): array
    {
        return [
            ['key' => 'max_file_size_mb', 'type' => 'number', 'label' => 'Max File Size (MB)', 'default' => 10, 'min' => 1],
        ];
    }

    public function withinLimit(mixed $value, array $config): bool
    {
        return $value <= (($config['max_file_size_mb'] ?? 0) * 1024 * 1024);
    }
}
