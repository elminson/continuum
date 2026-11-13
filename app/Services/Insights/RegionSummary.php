<?php

namespace App\Services\Insights;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\Instrument;
use App\Enums\ReferenceSensor;
use App\Models\Alert;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Services\Harmonization\HarmonizedCalendar;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Compact, cacheable summaries for dashboard cards and API listings.
 */
class RegionSummary
{
    public const string GLOBAL_KEY = 'continuum.global-summary';

    /** Seconds the global summary is served as fresh, then stale while it refreshes in the background. */
    public const int GLOBAL_FRESH_SECONDS = 600;

    public const int GLOBAL_STALE_SECONDS = 86400;

    public const int REGION_TTL_MINUTES = 30;

    public function __construct(private readonly HarmonizedCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Region $region): array
    {
        // Everything below only changes when the region is re-harmonized, which
        // stamps harmonized_at, so that timestamp versions the cache entry.
        $version = $region->harmonized_at?->getTimestamp() ?? 0;

        return Cache::remember(
            "continuum.region-summary.{$region->id}.{$version}.".app()->getLocale(),
            now()->addMinutes(self::REGION_TTL_MINUTES),
            fn (): array => $this->build($region),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Region $region): array
    {
        $daily = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->where('coverage_days', '>', 0)
            ->orderByDesc('period_start')
            ->limit(2 * 60)
            ->get();

        $byInstrument = $daily->groupBy(fn (CalendarEntry $entry) => $entry->instrument->value);
        $range = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->selectRaw('min(period_start) as first_date, max(period_start) filter (where cell_days > 0) as last_active')
            ->toBase()
            ->first();
        $first = $range->first_date ?? null;
        $lastActive = $range->last_active ?? null;
        $last = $daily->max(fn (CalendarEntry $entry) => $entry->period_start->toDateString());

        $factors = CalibrationFactor::query()
            ->where('region_id', $region->id)
            ->whereIn('method', [HarmonizationMethod::FrpThreshold->value, HarmonizationMethod::Ratio->value])
            ->where('reference', ReferenceSensor::Modis->value)
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->get()
            ->keyBy(fn (CalibrationFactor $factor) => $factor->method->value);

        $factor = $factors->get(HarmonizationMethod::FrpThreshold->value);
        $ratio = $factors->get(HarmonizationMethod::Ratio->value);

        $recent = $this->calendar->series($region, CalendarPeriod::Day, HarmonizationMethod::Ratio, ReferenceSensor::Modis, from: now()->subDays(30)->toDateString());

        // The strongest alert, with the region's alert count riding along.
        $topAlert = Alert::query()
            ->where('region_id', $region->id)
            ->select('period', 'period_start', 'severity', 'message', 'z_score', 'observed', 'expected', 'climatology_years', 'reference')
            ->selectRaw('count(*) over () as alert_count')
            ->orderByDesc('z_score')
            ->first();

        return [
            'first_date' => $first === null ? null : substr((string) $first, 0, 10),
            'last_date' => $last,
            'last_active_date' => $lastActive === null ? null : substr((string) $lastActive, 0, 10),
            'days_with_data' => $daily->pluck('period_start')->unique()->count(),
            'last_7_days' => [
                'modis' => $this->window($byInstrument->get(Instrument::Modis->value, collect()), 7),
                'viirs' => $this->window($byInstrument->get(Instrument::Viirs->value, collect()), 7),
            ],
            'sparkline' => array_map(fn (array $point) => $point['harmonized'], $recent['points']),
            'calibration' => [
                'calibrated' => $ratio !== null,
                'overlap_days' => $ratio?->sample_days,
                'ratio' => $ratio?->value,
                'ratio_ci' => $ratio === null ? null : [$ratio->ci_low, $ratio->ci_high],
                'frp_threshold_mw' => $factor?->value,
                'mae_improvement' => $factor?->validation['mae_improvement'] ?? $ratio?->validation['mae_improvement'] ?? null,
            ],
            'alerts' => (int) ($topAlert?->getAttribute('alert_count') ?? 0),
            'top_alert' => $topAlert?->only(['period_start', 'severity', 'message', 'z_score']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function global(): array
    {
        // Scanning every cell-day takes seconds once the archive is backfilled,
        // so visitors always get the cached figures; a stale copy is refreshed
        // after the response is sent, and continuum:warm-cache keeps it warm.
        return Cache::flexible(
            self::GLOBAL_KEY,
            [self::GLOBAL_FRESH_SECONDS, self::GLOBAL_STALE_SECONDS],
            fn (): array => $this->buildGlobal(),
        );
    }

    public function refreshGlobal(): void
    {
        Cache::putMany([
            self::GLOBAL_KEY => $this->buildGlobal(),
            Repository::FLEXIBLE_CREATED_KEY_PREFIX.self::GLOBAL_KEY => now()->getTimestamp(),
        ], self::GLOBAL_STALE_SECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildGlobal(): array
    {
        // min/max come straight off the acq_date index; the distinct-day count
        // and per-sensor totals use hash aggregates instead of count(distinct),
        // which would sort millions of rows on disk.
        $range = DB::table('cell_days')->selectRaw('min(acq_date) as first_date, max(acq_date) as last_date')->first();
        $days = (int) DB::query()->fromSub(DB::table('cell_days')->select('acq_date')->groupBy('acq_date'), 'days')->count();

        $perSatellite = DB::table('cell_days')
            ->selectRaw('instrument, satellite, count(*) as cell_days, sum(detections) as detections')
            ->groupBy('instrument', 'satellite')
            ->get();

        $instrument = function (string $name) use ($perSatellite): array {
            $rows = $perSatellite->where('instrument', $name);

            return [
                'detections' => (int) $rows->sum('detections'),
                'cell_days' => (int) $rows->sum('cell_days'),
                'satellites' => $rows->count(),
            ];
        };

        $modis = $instrument('modis');
        $viirs = $instrument('viirs');

        return [
            'first_date' => isset($range->first_date) ? substr((string) $range->first_date, 0, 10) : null,
            'last_date' => isset($range->last_date) ? substr((string) $range->last_date, 0, 10) : null,
            'days' => $days,
            'detections' => $modis['detections'] + $viirs['detections'],
            'cell_days' => $modis['cell_days'] + $viirs['cell_days'],
            'modis' => $modis,
            'viirs' => $viirs,
            'regions' => Region::query()->count(),
            'calibrated_regions' => CalibrationFactor::query()->distinct('region_id')->count('region_id'),
            'alerts' => Alert::query()->count(),
        ];
    }

    /**
     * @param  Collection<int, CalendarEntry>  $entries
     * @return array{cell_days: int, detections: int, frp_sum: float}
     */
    private function window(Collection $entries, int $days): array
    {
        $recent = $entries->sortByDesc(fn (CalendarEntry $entry) => $entry->period_start->toDateString())->take($days);

        return [
            'cell_days' => (int) $recent->sum('cell_days'),
            'detections' => (int) $recent->sum('detections'),
            'frp_sum' => round((float) $recent->sum('frp_sum'), 1),
        ];
    }
}
