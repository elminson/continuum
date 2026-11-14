<?php

namespace App\Services\Insights;

use App\Actions\Harmonization\CalibrateRegion;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Support\Stats\Statistics;
use Illuminate\Support\Facades\Cache;

/**
 * Simulates the end of MODIS: pretend it stopped on 1 January of a chosen
 * year, refit the VIIRS→MODIS monthly ratio on the overlap days before that
 * date only, then rebuild every later month from VIIRS alone and score it
 * against the MODIS record that actually exists. The test days never touch
 * the fit, so the error is what a VIIRS-only future would carry.
 */
class ModisRetirement
{
    private const int CONTEXT_YEARS = 3;

    private const int MIN_TRAIN_DAYS = 365;

    private const int MIN_TEST_DAYS = 180;

    public function __construct(private CalibrateRegion $calibrate) {}

    /**
     * Cutoff years with at least a year of overlap before and half a year after.
     *
     * @param  list<array{date: string, month: int, modis: int, viirs: int}>|null  $pairs
     * @return list<int>
     */
    public function years(Region $region, ?array $pairs = null): array
    {
        $pairs ??= $this->pairs($region);
        $years = [];

        if ($pairs === []) {
            return [];
        }

        $first = (int) substr($pairs[0]['date'], 0, 4);
        $last = (int) substr($pairs[count($pairs) - 1]['date'], 0, 4);

        for ($year = $first + 1; $year <= $last; $year++) {
            [$train, $test] = $this->split($pairs, $year);

            if (count($train) >= self::MIN_TRAIN_DAYS && count($test) >= self::MIN_TEST_DAYS) {
                $years[] = $year;
            }
        }

        return $years;
    }

    /**
     * @return array<string, mixed>
     */
    public function simulate(Region $region, ?int $year = null): array
    {
        $key = sprintf('modis-retirement:%d:%s:%s', $region->id, $year ?? 'default', $region->harmonized_at?->timestamp ?: 0);

        return Cache::remember($key, now()->addDay(), fn (): array => $this->compute($region, $year));
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Region $region, ?int $year): array
    {
        $pairs = $this->pairs($region);
        $years = $this->years($region, $pairs);

        if ($years === []) {
            return ['available' => false, 'years' => [], 'year' => null];
        }

        $year = in_array($year, $years, true) ? $year : $years[max(0, count($years) - 3)];
        [$train, $test] = $this->split($pairs, $year);
        $factors = $this->fit($train);

        $predicted = [];
        $months = [];

        foreach ($test as $pair) {
            $factor = $factors[$pair['month']] ?? $factors[CalibrationFactor::ALL_YEAR];
            $predicted[] = $pair['viirs'] * $factor['value'];
            $month = substr($pair['date'], 0, 7).'-01';
            $months[$month] ??= ['modis' => 0, 'viirs' => 0, 'estimate' => 0.0, 'low' => 0.0, 'high' => 0.0];
            $months[$month]['modis'] += $pair['modis'];
            $months[$month]['viirs'] += $pair['viirs'];
            $months[$month]['estimate'] += $pair['viirs'] * $factor['value'];
            $months[$month]['low'] += $pair['viirs'] * $factor['low'];
            $months[$month]['high'] += $pair['viirs'] * $factor['high'];
        }

        $observed = array_column($test, 'modis');
        $estimated = Statistics::errors($predicted, $observed);
        $naive = Statistics::errors(array_column($test, 'viirs'), $observed);
        $actualTotal = array_sum($observed);
        $estimatedTotal = array_sum($predicted);

        return [
            'available' => true,
            'years' => $years,
            'year' => $year,
            'cutoff' => "{$year}-01-01",
            'train_days' => count($train),
            'test_days' => count($test),
            'factor' => round($factors[CalibrationFactor::ALL_YEAR]['value'], 4),
            'actual_total' => $actualTotal,
            'estimated_total' => round($estimatedTotal, 1),
            'total_error' => $actualTotal > 0 ? round(($estimatedTotal - $actualTotal) / $actualTotal, 4) : null,
            'daily_mae' => round($estimated['mae'], 2),
            'naive_mae' => round($naive['mae'], 2),
            'mae_improvement' => $naive['mae'] > 0 ? round(1 - $estimated['mae'] / $naive['mae'], 4) : 0.0,
            'monthly_correlation' => round(Statistics::correlation(
                array_column($months, 'estimate'),
                array_column($months, 'modis'),
            ), 4),
            'points' => [...$this->context($train, $year), ...$this->points($months)],
        ];
    }

    /**
     * @return list<array{date: string, month: int, modis: int, viirs: int}>
     */
    private function pairs(Region $region): array
    {
        return array_map(
            fn (array $pair): array => ['date' => $pair['date'], 'month' => $pair['month'], 'modis' => $pair['modis'], 'viirs' => $pair['viirs']],
            $this->calibrate->overlapPairs($region),
        );
    }

    /**
     * @param  list<array{date: string, month: int, modis: int, viirs: int}>  $pairs
     * @return array{0: list<array{date: string, month: int, modis: int, viirs: int}>, 1: list<array{date: string, month: int, modis: int, viirs: int}>}
     */
    private function split(array $pairs, int $year): array
    {
        $cutoff = "{$year}-01-01";
        $train = array_values(array_filter($pairs, fn (array $pair): bool => $pair['date'] < $cutoff));
        $test = array_values(array_filter($pairs, fn (array $pair): bool => $pair['date'] >= $cutoff));

        return [$train, $test];
    }

    /**
     * Same transfer as the live calibration: an all-year ratio of sums plus
     * one per month with enough days, each with a bootstrap interval.
     *
     * @param  list<array{date: string, month: int, modis: int, viirs: int}>  $train
     * @return array<int, array{value: float, low: float, high: float, samples: int}>
     */
    private function fit(array $train): array
    {
        $minDays = (int) config('continuum.calibration.min_days', 5);
        $resamples = (int) config('continuum.calibration.bootstrap_samples', 400);
        $seed = (int) config('continuum.calibration.seed', 1);
        $ratio = fn (array $subset): array => Statistics::ratioOfSums(array_column($subset, 'modis'), array_column($subset, 'viirs'), $resamples, $seed);

        $factors = [CalibrationFactor::ALL_YEAR => $ratio($train)];
        $byMonth = [];

        foreach ($train as $pair) {
            $byMonth[$pair['month']][] = $pair;
        }

        foreach ($byMonth as $month => $subset) {
            if (count($subset) >= $minDays) {
                $factors[$month] = $ratio($subset);
            }
        }

        return $factors;
    }

    /**
     * The MODIS record in the years just before the simulated retirement.
     *
     * @param  list<array{date: string, month: int, modis: int, viirs: int}>  $train
     * @return list<array<string, mixed>>
     */
    private function context(array $train, int $year): array
    {
        $from = ($year - self::CONTEXT_YEARS).'-01-01';
        $months = [];

        foreach ($train as $pair) {
            if ($pair['date'] >= $from) {
                $month = substr($pair['date'], 0, 7).'-01';
                $months[$month] = ($months[$month] ?? 0) + $pair['modis'];
            }
        }

        return array_map(
            fn (string $month, int $modis): array => ['period_start' => $month, 'modis' => $modis, 'viirs' => null, 'estimate' => null, 'low' => null, 'high' => null, 'retired' => false],
            array_keys($months),
            array_values($months),
        );
    }

    /**
     * @param  array<string, array{modis: int, viirs: int, estimate: float, low: float, high: float}>  $months
     * @return list<array<string, mixed>>
     */
    private function points(array $months): array
    {
        return array_map(
            fn (string $month, array $row): array => [
                'period_start' => $month,
                'modis' => $row['modis'],
                'viirs' => $row['viirs'],
                'estimate' => round($row['estimate'], 1),
                'low' => round($row['low'], 1),
                'high' => round($row['high'], 1),
                'retired' => true,
            ],
            array_keys($months),
            array_values($months),
        );
    }
}
