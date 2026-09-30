<?php

namespace App\Http\Resources;

use App\Ai\Agents\AlertAnalyst;
use App\Models\Alert;
use App\Models\AlertTriage;
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
            'triage' => $this->whenLoaded('triage', fn () => $this->triagePayload($this->resource->triage)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function triagePayload(?AlertTriage $triage): ?array
    {
        if ($triage === null) {
            return null;
        }

        return [
            'cause' => $triage->cause,
            'cause_label' => AlertAnalyst::CAUSES[$triage->cause] ?? $triage->cause,
            'baseline_reliable' => $triage->baseline_reliable,
            'artifact_probability' => $triage->artifact_probability,
            'attention' => $triage->attention(),
            'attention_label' => AlertAnalyst::ATTENTION[$triage->attention()] ?? null,
            'rationale' => $triage->rationale,
            'model' => $triage->model,
            'provider' => $triage->provider,
            'triaged_at' => $triage->triaged_at->toIso8601String(),
            'advisory' => true,
        ];
    }
}
