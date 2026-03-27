<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CliInstallationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'abilities' => $this->abilities,
            'last_used_at' => $this->last_used_at,
            'last_used_ago' => $this->last_used_at?->diffForHumans(),
            'created_at' => $this->created_at,
            'created_ago' => $this->created_at->diffForHumans(),
        ];
    }
}
