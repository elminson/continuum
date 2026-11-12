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

        $transfer = $this->transfer(
            $referenceEntry?->coverage_days > 0 ? $referenceEntry->cell_days : null,
            $otherEntry?->coverage_days > 0 ? $otherEntry->cell_days : null,
            fn (): array => $otherEntry->frp_histogram ?? [],
            $factor,
            $method,
        );
        [$harmonized, $low, $high, $estimated] = $transfer ?? [null, null, null, false];
        $source = $transfer === null ? null : ($estimated ? $reference->other()->value : $reference->value);

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
     * Harmonized cell-days and raw hotspots summed over each date window,
     * read in one query without hydrating models. Used where only totals are
     * needed (comparisons over many years), so it stays fast on long records.
     *
     * @param  list<array{0: string, 1: string}>  $windows  inclusive [from, to] dates
     * @return list<array{harmonized: float, hotspots: int}>
     */
    public function dailyTotals(Region $region, HarmonizationMethod $method, ReferenceSensor $reference, array $windows): array
    {
        if ($method === HarmonizationMethod::FrpThreshold && $reference !== ReferenceSensor::Modis) {
            $method = HarmonizationMethod::Ratio;
        }

        $totals = array_map(fn (): array => ['harmonized' => 0.0, 'hotspots' => 0], $windows);

        if ($windows === []) {
            return $totals;
        }

        $factors = $this->factors($region, $method, $reference);
        $days = [];

        CalendarEntry::query()
            ->toBase()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->whereBetween('period_start', [min(array_column($windows, 0)), max(array_column($windows, 1))])
            ->where(function ($query) use ($windows): void {
                foreach ($windows as [$from, $to]) {
                    $query->orWhereBetween('period_start', [$from, $to]);
                }
            })
            ->orderBy('period_start')
            ->get(['period_start', 'instrument', 'cell_days', 'detections', 'coverage_days', 'frp_histogram'])
            ->each(function (object $row) use (&$days): void {
                $days[substr((string) $row->period_start, 0, 10)][$row->instrument] = $row;
            });

        foreach ($days as $date => $byInstrument) {
            $month = (int) substr($date, 5, 2);
            $referenceRow = $byInstrument[$reference->value] ?? null;
            $otherRow = $byInstrument[$reference->other()->value] ?? null;

            $transfer = $this->transfer(
                $referenceRow?->coverage_days > 0 ? (int) $referenceRow->cell_days : null,
                $otherRow?->coverage_days > 0 ? (int) $otherRow->cell_days : null,
                fn (): array => $this->decodeHistogram($otherRow->frp_histogram ?? null),
                $factors[$month] ?? $factors[CalibrationFactor::ALL_YEAR] ?? null,
                $method,
            );
            $hotspots = array_sum(array_map(fn (object $row) => $row->coverage_days > 0 ? (int) $row->detections : 0, $byInstrument));

            foreach ($windows as $index => [$from, $to]) {
                if ($date >= $from && $date <= $to) {
                    $totals[$index] = [
                        'harmonized' => $totals[$index]['harmonized'] + ($transfer[0] ?? 0.0),
                        'hotspots' => $totals[$index]['hotspots'] + $hotspots,
                    ];
                }
            }
        }

        return $totals;
    }

    /**
     * @return list<int>
     */
    private function decodeHistogram(mixed $json): array
    {
        $counts = is_string($json) ? json_decode($json, true) : null;

        return is_array($counts) ? array_values(array_map(intval(...), $counts)) : [];
    }

    /**
     * The reference sensor's own count when it flew, otherwise the other
     * sensor transferred onto the reference scale.
     *
     * @param  \Closure(): list<int>  $otherHistogram  resolved only for the FRP-threshold transfer
     * @param  array{value: float, low: float, high: float}|null  $factor
     * @return array{0: float, 1: float, 2: float, 3: bool}|null value, low, high, estimated
     */
    private function transfer(?int $referenceCellDays, ?int $otherCellDays, \Closure $otherHistogram, ?array $factor, HarmonizationMethod $method): ?array
    {
        if ($referenceCellDays !== null) {
            return [(float) $referenceCellDays, (float) $referenceCellDays, (float) $referenceCellDays, false];
        }

        if ($otherCellDays === null || $factor === null) {
            return null;
        }

        if ($method === HarmonizationMethod::FrpThreshold) {
            $value = (float) FrpHistogram::countAtOrAbove($otherHistogram(), $factor['value']);

            return [$value, $value, $value, true];
        }

        return [$otherCellDays * $factor['value'], $otherCellDays * $factor['low'], $otherCellDays * $factor['high'], true];
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
