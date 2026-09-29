<?php

namespace App\Http\Resources;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Region
 */
class RegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'bbox' => $this->bbox()->toArray(),
            'center' => $this->bbox()->center(),
            'area_km2' => round($this->areaKm2()),
            'color' => $this->color,
            'is_preset' => $this->is_preset,
            'is_owner' => $request->user() !== null && $request->user()->id === $this->user_id,
            'harmonized_at' => $this->harmonized_at?->toIso8601String(),
            'summary' => $this->whenHas('summary'),
            'links' => [
                'web' => route('regions.show', $this->resource),
                'lab' => route('regions.lab', $this->resource),
                'api' => route('api.v1.regions.show', $this->resource),
                'calendar' => route('api.v1.regions.calendar', $this->resource),
            ],
        ];
    }
}
