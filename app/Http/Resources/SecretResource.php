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
            'retrieved_at' => $this->retrieved_at,
            'masked_recipient_email' => $this->masked_recipient_email,
            'is_file' => $this->filepath !== null || $this->file_mime_type !== null,
            'file_mime_type' => $this->file_mime_type,
            'file_size' => $this->file_size,
        ];
    }
}
