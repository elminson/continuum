<?php

namespace App\Actions\Harmonization;

use App\Enums\AlertSeverity;
use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\Alert;
use App\Models\Region;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Support\Stats\Statistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Flags periods whose harmonized burning activity is unusual against the
 * same period in other years. Robust statistics (median and MAD) keep one
 * extreme year from hiding the next.
 */
class DetectAnomalies
{
    /** Share of a period's days that must have been observed for it to count. */
    public const float MIN_COVERAGE = 0.5;

    public function __construct(private readonly HarmonizedCalendar $calendar) {}

    /**
     * @return int number of alerts written
     */
    public function __invoke(
        Region $region,
        CalendarPeriod $period = CalendarPeriod::Week,
        HarmonizationMethod $method = HarmonizationMethod::Ratio,
        ReferenceSensor $reference = ReferenceSensor::Modis,
    ): int {
        $series = $this->calendar->series($region, $period, $method, $reference);
        $minYears = (int) config('continuum.anomalies.min_years', 3);

        $byIndex = [];

        foreach ($series['points'] as $point) {
            if (! $this->wellCovered($point, $period)) {
                continue;
            }

            $start = CarbonImmutable::parse($point['period_start']);
            $byIndex[$period->seasonalIndex($start)][$start->year] = $point['harmonized'];
        }

        $alerts = [];
        $now = now();

        foreach ($series['points'] as $point) {
            if (! $this->wellCovered($point, $period)) {
                continue;
            }

            $start = CarbonImmutable::parse($point['period_start']);
            $index = $period->seasonalIndex($start);
            $reference_years = $byIndex[$index] ?? [];
            unset($reference_years[$start->year]);

            if (count($reference_years) < $minYears) {
                continue;
            }

            $values = array_values($reference_years);
            $expected = Statistics::median($values);
            $spread = Statistics::robustSpread($values);

            if ($spread <= 0) {
                $spread = max(1.0, 0.25 * $expected);
            }

            $z = ($point['harmonized'] - $expected) / $spread;
            $severity = AlertSeverity::fromZScore($z);

            if ($severity === null) {
                continue;
            }

            $alerts[] = [
                'region_id' => $region->id,
                'period' => $period->value,
                'period_start' => $point['period_start'],
                'metric' => CalibrateRegion::METRIC,
                'method' => $series['method'],
                'reference' => $series['reference'],
                'observed' => round($point['harmonized'], 2),
                'expected' => round($expected, 2),
                'spread' => round($spread, 2),
                'z_score' => round($z, 3),
                'percentile' => round(Statistics::percentileRank($point['harmonized'], $values), 1),
                'severity' => $severity->value,
                'climatology_years' => count($values),
                'message' => $this->message($period, $start, $point['harmonized'], $expected, $z, count($values), $reference),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($region, $period, $series, $alerts): void {
            Alert::query()
                ->where('region_id', $region->id)
                ->where('period', $period->value)
                ->where('method', $series['method'])
                ->where('reference', $series['reference'])
                ->delete();

            foreach (array_chunk($alerts, 500) as $chunk) {
                Alert::query()->insert($chunk);
            }
        });

        return count($alerts);
    }

    /**
     * Only compare periods we actually observed for most of their length: a
     * week with one fetched day is not a quiet week, it is an unknown one.
     *
     * @param  array<string, mixed>  $point
     */
    private function wellCovered(array $point, CalendarPeriod $period): bool
    {
        if ($point['harmonized'] === null || $point['source'] === null) {
            return false;
        }

        $coverage = (int) ($point[$point['source']]['coverage_days'] ?? 0);
        $start = CarbonImmutable::parse($point['period_start']);
        $length = (int) $start->diffInDays($period->next($start));

        return $coverage >= (int) ceil(self::MIN_COVERAGE * $length);
    }

    private function message(CalendarPeriod $period, CarbonImmutable $start, float $observed, float $expected, float $z, int $years, ReferenceSensor $reference): string
    {
        $label = match ($period) {
            CalendarPeriod::Day => $start->format('j M Y'),
            CalendarPeriod::Week => 'Week of '.$start->format('j M Y'),
            CalendarPeriod::Month => $start->format('F Y'),
        };

        $multiple = $expected > 0 ? round($observed / $expected, 1) : null;
        $scale = $reference === ReferenceSensor::Modis ? 'MODIS-equivalent' : 'VIIRS-equivalent';

        return sprintf(
            '%s: %s %s cell-days, %s the median of %d other years (z = %s).',
            $label,
            number_format($observed),
            $scale,
            $multiple === null ? 'far above' : $multiple.'x',
            $years,
            number_format($z, 1),
        );
    }
}
