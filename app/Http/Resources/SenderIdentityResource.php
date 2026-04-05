<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SenderIdentityResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'company_name' => $this->company_name,
            'domain' => $this->domain,
            'email' => $this->email,
            'verification_token' => $this->verification_token,
            'is_verified' => $this->isVerified(),
            'has_active_retry' => $this->hasActiveRetry(),
        ];
    }
}
