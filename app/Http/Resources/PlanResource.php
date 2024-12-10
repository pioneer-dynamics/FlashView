<?php

namespace App\Http\Resources;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
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
        return array_merge(parent::toArray($request), [
            'settings' => $this->getSettings(),
            'features' => $this->when(isset($this->resource['features']),  collect(array_map(function($feature) {
                return [
                    'label' => __($feature['label'], $feature['config']),
                    'type' => $feature['type'],
                    'order' => $feature['order']
                ];
            }, $this->resource['features']))->sortBy('order'))
        ]);

        
    }

    private function getSettings()
    {
        $settings = [];

        if (!isset($this->resource['features'])) {
            return $settings;        
        }

        foreach($this->resource['features'] as $type => $feature) {
            $settings[$type] = $feature['config'];
        }

        return $settings;
    }
}
