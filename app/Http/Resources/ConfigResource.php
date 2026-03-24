<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfigResource extends JsonResource
{
    /**
     * Wrap the resource in an envelope.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'expiry_options' => $this->resource['expiry_options'],
            'max_expiry' => $this->resource['max_expiry'],
            'max_message_length' => $this->resource['max_message_length'],
        ];
    }
}
