<?php

namespace App\Http\Resources;

use App\Models\CalibrationFactor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalibrationFactor
 */
class CalibrationFactorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference->value,
            'month' => $this->month === CalibrationFactor::ALL_YEAR ? null : $this->month,
            'metric' => $this->metric,
            'value' => $this->value,
            'unit' => $this->method->value === 'frp_threshold' ? 'MW' : 'ratio',
            'ci95' => [$this->ci_low, $this->ci_high],
            'sample_days' => $this->sample_days,
            'overlap' => [$this->overlap_from?->toDateString(), $this->overlap_to?->toDateString()],
            'validation' => $this->validation,
            'version' => $this->version,
            'calibrated_at' => $this->calibrated_at->toIso8601String(),
        ];
    }
}
