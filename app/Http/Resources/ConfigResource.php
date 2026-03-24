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
        $data = [
            'expiry_options' => $this->resource['expiry_options'],
            'expiry_limits' => $this->resource['expiry_limits'],
            'message_length' => $this->resource['message_length'],
        ];

        if (isset($this->resource['plan_limits'])) {
            $data['plan_limits'] = $this->resource['plan_limits'];
        }

        return $data;
    }
}
