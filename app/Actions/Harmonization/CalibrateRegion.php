<?php

namespace App\Actions\Harmonization;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\Instrument;
use App\Enums\ReferenceSensor;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Support\Stats\FrpHistogram;
use App\Support\Stats\Statistics;
use Illuminate\Support\Facades\DB;

/**
 * Fits the transfer functions between MODIS and VIIRS for one region on the
 * days both instruments observed it, validates them on held-out days and
 * stores the resulting factors.
 *
 * Validation uses an interleaved holdout (every fourth overlap day) so that
 * every season is represented in both the training and the test set. The
 * stored factor is refitted on all overlap days after validation.
 */
class CalibrateRegion
{
    public const string METRIC = 'cell_days';

    private const int HOLDOUT_EVERY = 4;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(Region $region): array
    {
        $pairs = $this->overlapPairs($region);
        $minDays = (int) config('continuum.calibration.min_days', 5);

        $activeDays = count(array_filter($pairs, fn (array $pair) => $pair['modis'] + $pair['viirs'] > 0));

        if (count($pairs) < $minDays || $activeDays < $minDays) {
            return [[
                'status' => count($pairs) < $minDays ? 'insufficient_overlap' : 'no_activity',
                'overlap_days' => count($pairs),
                'active_days' => $activeDays,
                'required_days' => $minDays,
            ]];
        }

        [$train, $test] = $this->split($pairs);
        $results = [];

        foreach (ReferenceSensor::cases() as $reference) {
            $results[] = $this->calibrateRatio($region, $reference, $pairs, $train, $test);
        }

        $results[] = $this->calibrateFrpThreshold($region, $pairs, $train, $test);

        return $results;
    }

    /**
     * Overlap days: dates on which both instruments had global coverage.
     *
     * @return list<array{date: string, month: int, modis: int, viirs: int, viirs_histogram: list<int>}>
     */
    public function overlapPairs(Region $region): array
    {
        $entries = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->where('period_start', '>=', Instrument::Viirs->recordStart())
            ->where('coverage_days', '>', 0)
            ->orderBy('period_start')
            ->get()
            ->groupBy(fn (CalendarEntry $entry): string => $entry->period_start->toDateString());

        $pairs = [];

        foreach ($entries as $date => $group) {
            $date = (string) $date;
            $modis = $group->firstWhere('instrument', Instrument::Modis);
            $viirs = $group->firstWhere('instrument', Instrument::Viirs);

            if ($modis === null || $viirs === null) {
                continue;
            }

            $pairs[] = [
                'date' => $date,
                'month' => (int) substr($date, 5, 2),
                'modis' => $modis->cell_days,
                'viirs' => $viirs->cell_days,
                'viirs_histogram' => $viirs->frp_histogram ?? FrpHistogram::empty(),
            ];
        }

        return $pairs;
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function split(array $pairs): array
    {
        $train = [];
        $test = [];

        foreach ($pairs as $index => $pair) {
            if (($index + 1) % self::HOLDOUT_EVERY === 0 && count($pairs) >= 2 * self::HOLDOUT_EVERY) {
                $test[] = $pair;
            } else {
                $train[] = $pair;
            }
        }

        return [$train, $test === [] ? $train : $test];
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     * @param  list<array<string, mixed>>  $train
     * @param  list<array<string, mixed>>  $test
     * @return array<string, mixed>
     */
    private function calibrateRatio(Region $region, ReferenceSensor $reference, array $pairs, array $train, array $test): array
    {
        $numeratorKey = $reference->value;
        $denominatorKey = $reference->other()->value;
        $minDays = (int) config('continuum.calibration.min_days', 5);
        $resamples = (int) config('continuum.calibration.bootstrap_samples', 400);
        $seed = (int) config('continuum.calibration.seed', 1);

        $fit = fn (array $subset): array => Statistics::ratioOfSums(
            array_column($subset, $numeratorKey),
            array_column($subset, $denominatorKey),
            $resamples,
            $seed,
        );

        $trainFactors = $this->factorsByMonth($train, $fit, $minDays);
        $predicted = array_map(
            fn (array $pair) => $pair[$denominatorKey] * ($trainFactors[$pair['month']]['value'] ?? $trainFactors[0]['value']),
            $test,
        );
        $observed = array_column($test, $numeratorKey);
        $naive = array_column($test, $denominatorKey);

        $validation = $this->validation($predicted, $observed, $naive, count($train), count($test));
        $finalFactors = $this->factorsByMonth($pairs, $fit, $minDays);

        $this->store($region, HarmonizationMethod::Ratio, $reference, $finalFactors, $pairs, $validation);

        return [
            'status' => 'ok',
            'method' => HarmonizationMethod::Ratio->value,
            'reference' => $reference->value,
            'factor' => $finalFactors[0]['value'],
            'ci' => [$finalFactors[0]['low'], $finalFactors[0]['high']],
            'months' => count($finalFactors) - 1,
            'overlap_days' => count($pairs),
            'validation' => $validation,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pairs
     * @param  list<array<string, mixed>>  $train
     * @param  list<array<string, mixed>>  $test
     * @return array<string, mixed>
     */
    private function calibrateFrpThreshold(Region $region, array $pairs, array $train, array $test): array
    {
        $minDays = (int) config('continuum.calibration.min_days', 5);

        $fit = function (array $subset): array {
            $best = ['value' => 0.0, 'mae' => INF];

            foreach (FrpHistogram::EDGES as $tau) {
                $errors = 0.0;

                foreach ($subset as $pair) {
                    $errors += abs(FrpHistogram::countAtOrAbove($pair['viirs_histogram'], $tau) - $pair['modis']);
                }

                $mae = $errors / max(1, count($subset));

                if ($mae < $best['mae']) {
                    $best = ['value' => (float) $tau, 'mae' => $mae];
                }
            }

            return ['value' => $best['value'], 'low' => $best['value'], 'high' => $best['value'], 'samples' => count($subset)];
        };

        $trainFactors = $this->factorsByMonth($train, $fit, $minDays);
        $predicted = array_map(
            fn (array $pair) => FrpHistogram::countAtOrAbove($pair['viirs_histogram'], $trainFactors[$pair['month']]['value'] ?? $trainFactors[0]['value']),
            $test,
        );
        $observed = array_column($test, 'modis');
        $naive = array_column($test, 'viirs');

        $validation = $this->validation($predicted, $observed, $naive, count($train), count($test));
        $finalFactors = $this->factorsByMonth($pairs, $fit, $minDays);

        $this->store($region, HarmonizationMethod::FrpThreshold, ReferenceSensor::Modis, $finalFactors, $pairs, $validation);

        return [
            'status' => 'ok',
            'method' => HarmonizationMethod::FrpThreshold->value,
            'reference' => ReferenceSensor::Modis->value,
            'factor' => $finalFactors[0]['value'],
            'ci' => [$finalFactors[0]['low'], $finalFactors[0]['high']],
            'months' => count($finalFactors) - 1,
            'overlap_days' => count($pairs),
            'validation' => $validation,
        ];
    }

    /**
     * All-year factor (key 0) plus one factor per month with enough days.
     *
     * @param  list<array<string, mixed>>  $pairs
     * @param  callable(list<array<string, mixed>>): array{value: float, low: float, high: float, samples: int}  $fit
     * @return array<int, array{value: float, low: float, high: float, samples: int}>
     */
    private function factorsByMonth(array $pairs, callable $fit, int $minDays): array
    {
        $factors = [CalibrationFactor::ALL_YEAR => $fit($pairs)];
        $byMonth = [];

        foreach ($pairs as $pair) {
            $byMonth[$pair['month']][] = $pair;
        }

        foreach ($byMonth as $month => $subset) {
            if (count($subset) >= $minDays) {
                $factors[$month] = $fit($subset);
            }
        }

        ksort($factors);

        return $factors;
    }

    /**
     * @param  list<float|int>  $predicted
     * @param  list<float|int>  $observed
     * @param  list<float|int>  $naive
     * @return array<string, mixed>
     */
    private function validation(array $predicted, array $observed, array $naive, int $trainDays, int $testDays): array
    {
        $harmonized = Statistics::errors($predicted, $observed);
        $baseline = Statistics::errors($naive, $observed);
        $improvement = $baseline['mae'] > 0 ? 1 - ($harmonized['mae'] / $baseline['mae']) : 0.0;

        return [
            'train_days' => $trainDays,
            'test_days' => $testDays,
            'holdout' => 'interleaved (every '.self::HOLDOUT_EVERY.'th overlap day)',
            'harmonized' => $harmonized,
            'naive' => $baseline,
            'mae_improvement' => round($improvement, 4),
            'correlation' => round(Statistics::correlation($predicted, $observed), 4),
        ];
    }

    /**
     * @param  array<int, array{value: float, low: float, high: float, samples: int}>  $factors
     * @param  list<array<string, mixed>>  $pairs
     * @param  array<string, mixed>  $validation
     */
    private function store(Region $region, HarmonizationMethod $method, ReferenceSensor $reference, array $factors, array $pairs, array $validation): void
    {
        $now = now();
        $from = $pairs[0]['date'];
        $to = $pairs[count($pairs) - 1]['date'];

        DB::transaction(function () use ($region, $method, $reference, $factors, $validation, $now, $from, $to): void {
            CalibrationFactor::query()
                ->where('region_id', $region->id)
                ->where('method', $method->value)
                ->where('reference', $reference->value)
                ->where('metric', self::METRIC)
                ->delete();

            foreach ($factors as $month => $factor) {
                CalibrationFactor::query()->create([
                    'region_id' => $region->id,
                    'method' => $method,
                    'reference' => $reference,
                    'month' => $month,
                    'metric' => self::METRIC,
                    'value' => $this->finite($factor['value']),
                    'ci_low' => $this->finite($factor['low']),
                    'ci_high' => $this->finite($factor['high']),
                    'sample_days' => $factor['samples'],
                    'overlap_from' => $from,
                    'overlap_to' => $to,
                    'validation' => $month === CalibrationFactor::ALL_YEAR ? $validation : null,
                    'version' => config('continuum.calibration.method_version'),
                    'calibrated_at' => $now,
                ]);
            }
        });
    }

    private function finite(float $value): float
    {
        return is_finite($value) ? round($value, 4) : 99999999.9999;
    }
}
