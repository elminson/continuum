<?php

namespace App\Services\Insights;

use App\Enums\CalendarPeriod;
use App\Models\Alert;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Everything behind one flagged period: where the fire was (every active
 * 1 km cell over the period), how it built up day by day, and how the same
 * week or month looked in every other year of the record.
 */
class AlertDetail
{
    public const int MAX_CELLS = 8000;

    public function __construct(private readonly HarmonizedCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Alert $alert): array
    {
        $alert->loadMissing('region');
        $box = $alert->region->bbox();
        $from = $alert->period_start;
        $to = $alert->period->next($from)->subDay();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'footprint' => $this->footprint($box, $from, $to),
            'daily' => $this->daily($box, $from, $to),
            'history' => $this->history($alert),
        ];
    }

    /**
     * Active cells over the period, one point per 1 km cell.
     *
     * @return array<string, mixed>
     */
    private function footprint(BoundingBox $box, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cells = DB::table('cell_days')
            ->whereBetween('acq_date', [$from->toDateString(), $to->toDateString()])
            ->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east])
            ->groupBy('cell_id')
            ->selectRaw('cell_id, avg(latitude) as lat, avg(longitude) as lon')
            ->selectRaw('count(distinct acq_date) as days, sum(detections) as detections')
            ->selectRaw('max(frp_max) as frp_max, sum(frp_sum) as frp_sum')
            ->selectRaw("bool_or(instrument = 'modis') as modis, bool_or(instrument = 'viirs') as viirs")
            ->selectRaw('min(acq_date) as first_day, max(acq_date) as last_day')
            ->orderByDesc('frp_sum')
            ->limit(self::MAX_CELLS + 1)
            ->get();

        $truncated = $cells->count() > self::MAX_CELLS;
        $cells = $cells->take(self::MAX_CELLS);

        $features = $cells->map(fn (object $cell) => [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $cell->lon, 5), round((float) $cell->lat, 5)]],
            'properties' => [
                'days' => (int) $cell->days,
                'detections' => (int) $cell->detections,
                'frp_max' => round((float) $cell->frp_max, 1),
                'frp_sum' => round((float) $cell->frp_sum, 1),
                'sensors' => (filter_var($cell->modis, FILTER_VALIDATE_BOOL) ? 'M' : '').(filter_var($cell->viirs, FILTER_VALIDATE_BOOL) ? 'V' : ''),
                'first' => substr((string) $cell->first_day, 0, 10),
                'last' => substr((string) $cell->last_day, 0, 10),
            ],
        ])->values();

        $bounds = null;

        if ($cells->isNotEmpty()) {
            $pad = 0.15;
            $bounds = [
                max($box->west, (float) $cells->min('lon') - $pad),
                max($box->south, (float) $cells->min('lat') - $pad),
                min($box->east, (float) $cells->max('lon') + $pad),
                min($box->north, (float) $cells->max('lat') + $pad),
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'cells' => $cells->count(),
            'truncated' => $truncated,
            'bounds' => $bounds,
            'peak_cell' => $features->first(),
        ];
    }

    /**
     * Active cells per day and instrument across the period.
     *
     * @return list<array{date: string, modis: int, viirs: int}>
     */
    private function daily(BoundingBox $box, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('cell_days')
            ->whereBetween('acq_date', [$from->toDateString(), $to->toDateString()])
            ->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east])
            ->groupBy('acq_date', 'instrument')
            ->selectRaw('acq_date, instrument, count(distinct cell_id) as cells')
            ->get()
            ->groupBy(fn (object $row) => substr((string) $row->acq_date, 0, 10));

        $days = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $group = $rows->get($date, collect());
            $days[] = [
                'date' => $date,
                'modis' => (int) ($group->firstWhere('instrument', 'modis')->cells ?? 0),
                'viirs' => (int) ($group->firstWhere('instrument', 'viirs')->cells ?? 0),
            ];
        }

        return $days;
    }

    /**
     * The same week or month in every year of the harmonized record.
     *
     * @return list<array{year: int, period_start: string, value: float|null, estimated: bool, current: bool}>
     */
    private function history(Alert $alert): array
    {
        $series = $this->calendar->series($alert->region, $alert->period, $alert->method, $alert->reference);
        $index = $alert->period->seasonalIndex($alert->period_start);
        $years = [];

        foreach ($series['points'] as $point) {
            $start = CarbonImmutable::parse($point['period_start']);

            if ($alert->period->seasonalIndex($start) !== $index || $alert->period === CalendarPeriod::Day && $start->month !== $alert->period_start->month) {
                continue;
            }

            $years[$start->year] = [
                'year' => $start->year,
                'period_start' => $point['period_start'],
                'value' => $point['harmonized'],
                'estimated' => (bool) $point['estimated'],
                'current' => $point['period_start'] === $alert->period_start->toDateString(),
            ];
        }

        ksort($years);

        return array_values($years);
    }
}
