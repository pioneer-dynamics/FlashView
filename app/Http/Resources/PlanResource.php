<?php

namespace App\Http\Resources;

use App\Services\FeatureRegistry;
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
        $registry = app(FeatureRegistry::class);

        $features = collect($this->resource['features'] ?? [])
            ->filter(fn ($feature) => ($feature['type'] ?? 'missing') !== 'missing')
            ->filter(fn ($feature, $key) => $registry->has($key))
            ->map(function ($feature, $key) use ($registry) {
                $class = $registry->get($key);

                return [
                    'label' => $class->description(),
                    'type' => $feature['type'],
                    'order' => $feature['order'],
                ];
            })
            ->sortBy('order')
            ->values();

        return array_merge(parent::toArray($request), [
            'settings' => $this->getSettings(),
            'features' => $features,
        ]);
    }

    private function getSettings(): array
    {
        $settings = [];

        if (! isset($this->resource['features'])) {
            return $settings;
        }

        foreach ($this->resource['features'] as $type => $feature) {
            if (($feature['type'] ?? 'missing') === 'missing') {
                continue;
            }
            $settings[$type] = $feature['config'] ?? [];
        }

        return $settings;
    }
}
