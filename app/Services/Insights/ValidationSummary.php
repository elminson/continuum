<?php

namespace App\Services\Insights;

use App\Enums\HarmonizationMethod;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Models\User;

/**
 * Held-out validation of every visible area's all-year transfer, sorted from
 * the weakest fit to the strongest, with the reasons a fit is weak spelled out.
 */
class ValidationSummary
{
    /** Fewer overlap days than this and the factor rests on a sample, not a record. */
    public const int SMALL_SAMPLE_DAYS = 90;

    /** Below this held-out R² the daily transfer explains less than half the variance. */
    public const float WEAK_R2 = 0.5;

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(?User $user): array
    {
        $regions = Region::query()->visibleTo($user)->get()->keyBy('id');

        return array_values(CalibrationFactor::query()
            ->whereIn('region_id', $regions->keys())
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->where('method', HarmonizationMethod::Ratio->value)
            ->whereNotNull('validation')
            ->get()
            ->map(function (CalibrationFactor $factor) use ($regions): array {
                $validation = $factor->validation;
                $region = $regions[$factor->region_id];
                $r2 = $validation['harmonized']['r2'] ?? null;
                $issues = [];

                if ($factor->sample_days < self::SMALL_SAMPLE_DAYS) {
                    $issues[] = 'small_sample';
                }

                if ($r2 !== null && $r2 < self::WEAK_R2) {
                    $issues[] = 'weak_daily_fit';
                }

                if (($validation['mae_improvement'] ?? 1) < 0.25) {
                    $issues[] = 'barely_beats_naive';
                }

                return [
                    'region' => $region->name,
                    'slug' => $region->slug,
                    'area_km2' => round($region->areaKm2()),
                    'reference' => $factor->reference->value,
                    'overlap_days' => $factor->sample_days,
                    'test_days' => $validation['test_days'] ?? null,
                    'mae' => round((float) ($validation['harmonized']['mae'] ?? 0), 1),
                    'naive_mae' => round((float) ($validation['naive']['mae'] ?? 0), 1),
                    'improvement' => $validation['mae_improvement'] ?? null,
                    'r2' => $r2,
                    'correlation' => $validation['correlation'] ?? null,
                    'issues' => $issues,
                ];
            })
            ->sortBy([
                ['reference', 'asc'],
                fn (array $a, array $b) => ($a['r2'] ?? -INF) <=> ($b['r2'] ?? -INF),
            ])
            ->all());
    }
}
