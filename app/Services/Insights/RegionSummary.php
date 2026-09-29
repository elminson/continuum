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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Compact, cacheable summaries for dashboard cards and API listings.
 */
class RegionSummary
{
    public function __construct(private readonly HarmonizedCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Region $region): array
    {
        $daily = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->where('coverage_days', '>', 0)
            ->orderByDesc('period_start')
            ->limit(2 * 60)
            ->get();

        $byInstrument = $daily->groupBy(fn (CalendarEntry $entry) => $entry->instrument->value);
        $first = CalendarEntry::query()->where('region_id', $region->id)->where('period', 'day')->min('period_start');
        $last = $daily->max(fn (CalendarEntry $entry) => $entry->period_start->toDateString());

        $factor = CalibrationFactor::query()
            ->where('region_id', $region->id)
            ->where('method', HarmonizationMethod::FrpThreshold->value)
            ->where('reference', ReferenceSensor::Modis->value)
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->first();

        $ratio = CalibrationFactor::query()
            ->where('region_id', $region->id)
            ->where('method', HarmonizationMethod::Ratio->value)
            ->where('reference', ReferenceSensor::Modis->value)
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->first();

        $recent = $this->calendar->series($region, CalendarPeriod::Day, HarmonizationMethod::Ratio, ReferenceSensor::Modis, from: now()->subDays(30)->toDateString());

        $lastActive = CalendarEntry::query()
            ->where('region_id', $region->id)
            ->where('period', CalendarPeriod::Day->value)
            ->where('cell_days', '>', 0)
            ->max('period_start');

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
            'alerts' => Alert::query()->where('region_id', $region->id)->count(),
            'top_alert' => Alert::query()->where('region_id', $region->id)->orderByDesc('z_score')->first()?->only(['period_start', 'severity', 'message', 'z_score']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function global(): array
    {
        return Cache::remember('continuum.global-summary', now()->addMinutes(10), function (): array {
            $range = DB::table('cell_days')->selectRaw('min(acq_date) as first_date, max(acq_date) as last_date, count(distinct acq_date) as days')->first();
            $perInstrument = DB::table('cell_days')
                ->selectRaw('instrument, count(*) as cell_days, sum(detections) as detections, count(distinct satellite) as satellites')
                ->groupBy('instrument')
                ->get()
                ->keyBy('instrument');

            return [
                'first_date' => isset($range->first_date) ? substr((string) $range->first_date, 0, 10) : null,
                'last_date' => isset($range->last_date) ? substr((string) $range->last_date, 0, 10) : null,
                'days' => (int) ($range->days ?? 0),
                'detections' => (int) $perInstrument->sum('detections'),
                'cell_days' => (int) $perInstrument->sum('cell_days'),
                'modis' => [
                    'detections' => (int) ($perInstrument->get('modis')->detections ?? 0),
                    'cell_days' => (int) ($perInstrument->get('modis')->cell_days ?? 0),
                    'satellites' => (int) ($perInstrument->get('modis')->satellites ?? 0),
                ],
                'viirs' => [
                    'detections' => (int) ($perInstrument->get('viirs')->detections ?? 0),
                    'cell_days' => (int) ($perInstrument->get('viirs')->cell_days ?? 0),
                    'satellites' => (int) ($perInstrument->get('viirs')->satellites ?? 0),
                ],
                'regions' => Region::query()->count(),
                'calibrated_regions' => CalibrationFactor::query()->distinct('region_id')->count('region_id'),
                'alerts' => Alert::query()->count(),
            ];
        });
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
