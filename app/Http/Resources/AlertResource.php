<?php

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'region' => $this->whenLoaded('region', fn () => [
                'slug' => $this->region->slug,
                'name' => $this->region->name,
                'color' => $this->region->color,
            ]),
            'period' => $this->period->value,
            'period_start' => $this->period_start->toDateString(),
            'metric' => $this->metric,
            'method' => $this->method->value,
            'reference' => $this->reference->value,
            'observed' => $this->observed,
            'expected' => $this->expected,
            'spread' => $this->spread,
            'z_score' => $this->z_score,
            'percentile' => $this->percentile,
            'severity' => $this->severity->value,
            'climatology_years' => $this->climatology_years,
            'message' => $this->message,
        ];
    }
}
