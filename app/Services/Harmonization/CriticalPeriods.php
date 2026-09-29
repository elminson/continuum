<?php

namespace App\Services\Harmonization;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\Region;
use App\Support\Stats\Statistics;
use Carbon\CarbonImmutable;

/**
 * Derives a region's climatological fire season from its harmonized
 * calendar: the run of periods whose typical activity stays above a share
 * of the seasonal peak, plus the peak itself and the latest observation's
 * position within the season.
 */
class CriticalPeriods
{
    public const float SEASON_SHARE = 0.5;

    public const float ONSET_SHARE = 0.25;

    public function __construct(private readonly HarmonizedCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function forRegion(
        Region $region,
        CalendarPeriod $period = CalendarPeriod::Month,
        HarmonizationMethod $method = HarmonizationMethod::Ratio,
        ReferenceSensor $reference = ReferenceSensor::Modis,
    ): array {
        $series = $this->calendar->series($region, $period, $method, $reference);
        $indexCount = match ($period) {
            CalendarPeriod::Month => 12,
            CalendarPeriod::Week => 52,
            CalendarPeriod::Day => 366,
        };

        $samples = array_fill(1, $indexCount, []);
        $years = [];
        $latest = null;

        foreach ($series['points'] as $point) {
            if ($point['harmonized'] === null) {
                continue;
            }

            $start = CarbonImmutable::parse($point['period_start']);
            $index = min($indexCount, $period->seasonalIndex($start));
            $samples[$index][] = $point['harmonized'];
            $years[$start->year] = true;
            $latest = ['period_start' => $point['period_start'], 'index' => $index, 'value' => $point['harmonized']];
        }

        $climatology = [];
        $peakValue = 0.0;
        $peakIndex = null;

        foreach ($samples as $index => $values) {
            $typical = $values === [] ? null : Statistics::median($values);
            $climatology[] = [
                'index' => $index,
                'label' => $this->label($period, $index),
                'typical' => $typical === null ? null : round($typical, 1),
                'max' => $values === [] ? null : round(max($values), 1),
                'years' => count($values),
            ];

            if ($typical !== null && $typical > $peakValue) {
                $peakValue = $typical;
                $peakIndex = $index;
            }
        }

        $season = $peakIndex === null ? null : $this->season($climatology, $peakIndex, $peakValue, $indexCount);

        return [
            'period' => $period->value,
            'method' => $series['method'],
            'reference' => $series['reference'],
            'years' => count($years),
            'climatology' => $climatology,
            'peak' => $peakIndex === null ? null : ['index' => $peakIndex, 'label' => $this->label($period, $peakIndex), 'typical' => round($peakValue, 1)],
            'season' => $season,
            'latest' => $latest === null ? null : $latest + ['in_season' => $season !== null && $this->inRange($latest['index'], $season['start'], $season['end'], $indexCount)],
        ];
    }

    /**
     * @param  list<array{index: int, label: string, typical: float|null, max: float|null, years: int}>  $climatology
     * @return array{start: int, end: int, start_label: string, end_label: string, length: int, onset: int|null, onset_label: string|null}
     */
    private function season(array $climatology, int $peakIndex, float $peakValue, int $indexCount): array
    {
        $typical = array_column($climatology, 'typical', 'index');
        $threshold = $peakValue * self::SEASON_SHARE;
        $onsetThreshold = $peakValue * self::ONSET_SHARE;

        $start = $peakIndex;
        $steps = 0;

        while ($steps < $indexCount - 1) {
            $previous = $this->wrap($start - 1, $indexCount);

            if (($typical[$previous] ?? 0) < $threshold) {
                break;
            }

            $start = $previous;
            $steps++;
        }

        $end = $peakIndex;
        $steps = 0;

        while ($steps < $indexCount - 1) {
            $next = $this->wrap($end + 1, $indexCount);

            if (($typical[$next] ?? 0) < $threshold || $next === $start) {
                break;
            }

            $end = $next;
            $steps++;
        }

        $onset = $start;
        $steps = 0;

        while ($steps < $indexCount - 1) {
            $previous = $this->wrap($onset - 1, $indexCount);

            if (($typical[$previous] ?? 0) < $onsetThreshold || $previous === $end) {
                break;
            }

            $onset = $previous;
            $steps++;
        }

        $length = $end >= $start ? $end - $start + 1 : $indexCount - $start + $end + 1;
        $labels = array_column($climatology, 'label', 'index');

        return [
            'start' => $start,
            'end' => $end,
            'start_label' => $labels[$start],
            'end_label' => $labels[$end],
            'length' => $length,
            'onset' => $onset === $start ? null : $onset,
            'onset_label' => $onset === $start ? null : $labels[$onset],
        ];
    }

    private function wrap(int $index, int $count): int
    {
        return (($index - 1) % $count + $count) % $count + 1;
    }

    private function inRange(int $index, int $start, int $end, int $count): bool
    {
        if ($start <= $end) {
            return $index >= $start && $index <= $end;
        }

        return $index >= $start || $index <= $end;
    }

    private function label(CalendarPeriod $period, int $index): string
    {
        return match ($period) {
            CalendarPeriod::Month => CarbonImmutable::create(2001, $index, 1)->format('M'),
            CalendarPeriod::Week => 'W'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            CalendarPeriod::Day => 'D'.$index,
        };
    }
}
