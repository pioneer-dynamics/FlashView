<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecretResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'is_expired' => $this->expires_at->isPast(),
            'is_retrieved' => $this->retrieved_at !== null,
        ];
    }
}
