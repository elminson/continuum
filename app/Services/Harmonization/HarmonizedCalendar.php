<?php

namespace App\Services\Harmonization;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\Instrument;
use App\Enums\ReferenceSensor;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Support\Stats\FrpHistogram;
use Illuminate\Support\Collection;

/**
 * Combines a region's native per-instrument calendar with its calibration
 * factors into one continuous series expressed on the reference sensor's
 * scale. The reference sensor's own observations are always used when it
 * flew; the other sensor is transferred only where the reference is absent.
 */
class HarmonizedCalendar
{
    /**
     * @return array{
     *     points: list<array<string, mixed>>,
     *     factors: array<int, array{value: float, low: float, high: float}>,
     *     method: string,
     *     reference: string,
     *     period: string,
     *     calibrated: bool,
     *     validation: array<string, mixed>|null
     * }
     */
    public function series(
        Region $region,
        CalendarPeriod $period,
        HarmonizationMethod $method,
        ReferenceSensor $reference,
        ?string $from = null,
        ?string $to = null,
    ): array {
        if ($method === HarmonizationMethod::FrpThreshold && $reference !== ReferenceSensor::Modis) {
            $method = HarmonizationMethod::Ratio;
        }

        $factors = $this->factors($region, $method, $reference);

        $entries = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', $period->value)
            ->when($from, fn ($query) => $query->where('period_start', '>=', $from))
            ->when($to, fn ($query) => $query->where('period_start', '<=', $to))
            ->orderBy('period_start')
            ->get()
            ->groupBy(fn (CalendarEntry $entry) => $entry->period_start->toDateString());

        $points = [];

        foreach ($entries as $periodStart => $group) {
            $points[] = $this->point($periodStart, $group, $method, $reference, $factors);
        }

        $allYear = $factors[CalibrationFactor::ALL_YEAR] ?? null;

        return [
            'points' => $points,
            'factors' => array_map(fn (array $factor) => ['value' => $factor['value'], 'low' => $factor['low'], 'high' => $factor['high']], $factors),
            'method' => $method->value,
            'reference' => $reference->value,
            'period' => $period->value,
            'calibrated' => $allYear !== null,
            'validation' => $allYear['validation'] ?? null,
        ];
    }

    /**
     * @return array<int, array{value: float, low: float, high: float, samples: int, validation: array<string, mixed>|null}>
     */
    public function factors(Region $region, HarmonizationMethod $method, ReferenceSensor $reference): array
    {
        return CalibrationFactor::query()
            ->where('region_id', $region->id)
            ->where('method', $method->value)
            ->where('reference', $reference->value)
            ->get()
            ->mapWithKeys(fn (CalibrationFactor $factor) => [$factor->month => [
                'value' => $factor->value,
                'low' => $factor->ci_low ?? $factor->value,
                'high' => $factor->ci_high ?? $factor->value,
                'samples' => $factor->sample_days,
                'validation' => $factor->validation,
            ]])
            ->all();
    }

    /**
     * @param  Collection<int, CalendarEntry>  $group
     * @param  array<int, array{value: float, low: float, high: float}>  $factors
     * @return array<string, mixed>
     */
    private function point(string $periodStart, Collection $group, HarmonizationMethod $method, ReferenceSensor $reference, array $factors): array
    {
        $modis = $group->firstWhere('instrument', Instrument::Modis);
        $viirs = $group->firstWhere('instrument', Instrument::Viirs);
        $month = (int) substr($periodStart, 5, 2);
        $factor = $factors[$month] ?? $factors[CalibrationFactor::ALL_YEAR] ?? null;

        $referenceEntry = $reference === ReferenceSensor::Modis ? $modis : $viirs;
        $otherEntry = $reference === ReferenceSensor::Modis ? $viirs : $modis;

        $harmonized = null;
        $low = null;
        $high = null;
        $estimated = false;
        $source = null;

        if ($referenceEntry !== null && $referenceEntry->coverage_days > 0) {
            $harmonized = $low = $high = (float) $referenceEntry->cell_days;
            $source = $reference->value;
        } elseif ($otherEntry !== null && $otherEntry->coverage_days > 0 && $factor !== null) {
            $estimated = true;
            $source = $reference->other()->value;

            if ($method === HarmonizationMethod::FrpThreshold) {
                $harmonized = $low = $high = (float) FrpHistogram::countAtOrAbove($otherEntry->frp_histogram ?? [], $factor['value']);
            } else {
                $harmonized = $otherEntry->cell_days * $factor['value'];
                $low = $otherEntry->cell_days * $factor['low'];
                $high = $otherEntry->cell_days * $factor['high'];
            }
        }

        $naiveEntry = $viirs !== null && $viirs->coverage_days > 0 ? $viirs : $modis;

        return [
            'period_start' => $periodStart,
            'month' => $month,
            'modis' => $this->native($modis),
            'viirs' => $this->native($viirs),
            'harmonized' => $harmonized === null ? null : round($harmonized, 1),
            'low' => $low === null ? null : round($low, 1),
            'high' => $high === null ? null : round($high, 1),
            'estimated' => $estimated,
            'source' => $source,
            'factor' => $factor['value'] ?? null,
            'naive' => $naiveEntry?->coverage_days > 0 ? $naiveEntry->cell_days : null,
        ];
    }

    /**
     * @return array<string, int|float>|null
     */
    private function native(?CalendarEntry $entry): ?array
    {
        if ($entry === null || $entry->coverage_days === 0) {
            return null;
        }

        return [
            'detections' => $entry->detections,
            'cell_days' => $entry->cell_days,
            'active_cells' => $entry->active_cells,
            'frp_sum' => round($entry->frp_sum, 1),
            'frp_max' => round($entry->frp_max, 1),
            'footprint_km2' => round($entry->footprint_km2, 1),
            'day_detections' => $entry->day_detections,
            'night_detections' => $entry->night_detections,
            'high_confidence' => $entry->high_confidence,
            'active_days' => $entry->active_days,
            'coverage_days' => $entry->coverage_days,
        ];
    }
}
