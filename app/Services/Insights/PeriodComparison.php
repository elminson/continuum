<?php

namespace App\Services\Insights;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Support\Stats\Statistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Compares a date range with the same calendar dates in every earlier year
 * of the record: "this window had X, the median year had Y".
 *
 * Active-fire products are positive-only, so a day without a calendar entry
 * counts as zero. Only years whose shifted window starts on or after the
 * first day of the record are used as history.
 */
class PeriodComparison
{
    /** Longest window that still has a same-dates counterpart in other years. */
    public const int MAX_DAYS = 366;

    public const int DEFAULT_DAYS = 30;

    public function __construct(private readonly HarmonizedCalendar $calendar) {}

    /**
     * The window shown when the user has not picked one: the last
     * DEFAULT_DAYS days of the record.
     *
     * @return array{0: string, 1: string}|null
     */
    public function defaultWindow(?string $lastDate): ?array
    {
        if ($lastDate === null) {
            return null;
        }

        $to = CarbonImmutable::parse($lastDate);

        return [$to->subDays(self::DEFAULT_DAYS - 1)->toDateString(), $to->toDateString()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forRegion(
        Region $region,
        string $from,
        string $to,
        HarmonizationMethod $method = HarmonizationMethod::Ratio,
        ReferenceSensor $reference = ReferenceSensor::Modis,
    ): ?array {
        $version = $region->harmonized_at?->getTimestamp() ?? 0;

        return Cache::remember(
            "continuum.period-comparison.{$region->id}.{$version}.{$from}.{$to}.{$method->value}.{$reference->value}",
            now()->addMinutes(RegionSummary::REGION_TTL_MINUTES),
            fn (): ?array => $this->build($region, $from, $to, $method, $reference),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(Region $region, string $from, string $to, HarmonizationMethod $method, ReferenceSensor $reference): ?array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        $first = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->min('period_start');

        if ($first === null) {
            return null;
        }

        $firstDate = CarbonImmutable::parse($first)->startOfDay();

        // Window 0 is the selected range; the rest are the same dates in each
        // earlier year whose window starts inside the record.
        $windows = [[$start->toDateString(), $end->toDateString()]];

        if ($days <= self::MAX_DAYS) {
            for ($offset = 1; $start->subYears($offset)->gte($firstDate); $offset++) {
                $windows[] = [$start->subYears($offset)->toDateString(), $end->subYears($offset)->toDateString()];
            }
        }

        $totals = $this->calendar->dailyTotals($region, $method, $reference, $windows);

        $base = [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'days' => $days,
            'current' => round($totals[0]['harmonized'], 1),
            'hotspots' => $totals[0]['hotspots'],
            'reference' => $reference->value,
        ];

        if ($days > self::MAX_DAYS) {
            return $base + ['median' => null, 'change' => null, 'percentile' => null, 'years' => 0, 'history' => [], 'reason' => 'range_too_long'];
        }

        $history = [];

        foreach (array_slice($windows, 1, preserve_keys: true) as $index => [$from]) {
            $history[] = ['year' => (int) substr($from, 0, 4), 'value' => round($totals[$index]['harmonized'], 1)];
        }

        if ($history === []) {
            return $base + ['median' => null, 'change' => null, 'percentile' => null, 'years' => 0, 'history' => [], 'reason' => 'no_history'];
        }

        $values = array_column($history, 'value');
        $median = Statistics::median($values);

        return $base + [
            'median' => round($median, 1),
            'change' => $median > 0 ? round($base['current'] / $median - 1, 4) : null,
            'percentile' => round(Statistics::percentileRank($base['current'], $values), 1),
            'years' => count($history),
            'history' => array_reverse($history),
            'reason' => null,
        ];
    }
}
