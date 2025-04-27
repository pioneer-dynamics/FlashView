<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $features = collect();

        if (isset($this->resource['features'])) {
            $features = collect(array_map(function ($feature) {
                return [
                    'label' => __($feature['label'], $feature['config']),
                    'type' => $feature['type'],
                    'order' => $feature['order'],
                ];
            }, $this->resource['features']))->sortBy('order');
        }

        return array_merge(parent::toArray($request), [
            'settings' => $this->getSettings(),
            'features' => $features,
        ]);

    }

    private function getSettings()
    {
        $settings = [];

        if (! isset($this->resource['features'])) {
            return $settings;
        }

        foreach ($this->resource['features'] as $type => $feature) {
            $settings[$type] = $feature['config'];
        }

        return $settings;
    }
}
