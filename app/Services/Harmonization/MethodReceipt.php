<?php

namespace App\Services\Harmonization;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\CalibrationFactor;
use App\Models\IngestRun;
use App\Models\Region;

/**
 * Provenance attached to every export and API response: which data, grid,
 * method and calibration produced the numbers.
 */
class MethodReceipt
{
    /**
     * @return array<string, mixed>
     */
    public function for(Region $region, CalendarPeriod $period, HarmonizationMethod $method, ReferenceSensor $reference): array
    {
        $factor = CalibrationFactor::query()
            ->where('region_id', $region->id)
            ->where('method', $method->value)
            ->where('reference', $reference->value)
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->first();

        $runs = IngestRun::query()
            ->where('status', 'completed')
            ->orderByDesc('finished_at')
            ->limit(20)
            ->get(['source', 'instrument', 'date_from', 'date_to', 'rows_inserted', 'finished_at']);

        return [
            'application' => config('app.name').' '.config('continuum.calibration.method_version'),
            'generated_at' => now()->toIso8601String(),
            'region' => [
                'slug' => $region->slug,
                'name' => $region->name,
                'bbox' => $region->bbox()->toArray(),
                'area_km2' => round($region->areaKm2()),
            ],
            'grid' => [
                'harmonization_cell_km' => (float) config('continuum.grid_km'),
                'calendar_cell_deg' => (float) config('continuum.calendar_cell_deg'),
                'cell_day_definition' => 'distinct (cell, UTC day) per instrument; satellites of one instrument merged; FRP = strongest single-platform view',
            ],
            'period' => $period->value,
            'method' => [
                'name' => $method->value,
                'label' => $method->label(),
                'reference' => $reference->value,
                'version' => config('continuum.calibration.method_version'),
                'factor' => $factor?->value,
                'ci95' => $factor === null ? null : [$factor->ci_low, $factor->ci_high],
                'overlap' => $factor === null ? null : [$factor->overlap_from?->toDateString(), $factor->overlap_to?->toDateString()],
                'overlap_days' => $factor?->sample_days,
                'validation' => $factor?->validation,
                'calibrated_at' => $factor?->calibrated_at?->toIso8601String(),
            ],
            'sources' => [
                'modis' => 'NASA FIRMS MODIS Collection 6.1 active fire (Terra + Aqua, 1 km)',
                'viirs' => 'NASA FIRMS VIIRS Collection 2 375 m active fire (Suomi NPP, NOAA-20, NOAA-21)',
                'static_sources_excluded' => true,
                'ingest_runs' => $runs->map(fn (IngestRun $run) => [
                    'source' => $run->source,
                    'instrument' => $run->instrument?->value,
                    'from' => $run->date_from?->toDateString(),
                    'to' => $run->date_to?->toDateString(),
                    'rows' => $run->rows_inserted,
                ])->all(),
            ],
            'caveats' => [
                'Detections are positive-only: a period with no detections may be cloud, sensor outage or genuinely no fire. coverage_days states how many days the instrument reported anywhere on Earth.',
                'Harmonized values marked estimated are transferred from the other instrument with the stated factor and interval.',
                'Near-real-time (NRT) records are replaced by standard-processing records when a backfill runs; values can change slightly.',
            ],
        ];
    }
}
