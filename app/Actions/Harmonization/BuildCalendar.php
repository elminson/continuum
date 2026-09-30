<?php

namespace App\Actions\Harmonization;

use App\Enums\CalendarPeriod;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Support\Db\PeriodExpression;
use App\Support\Stats\FrpHistogram;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Builds the native per-instrument burning activity calendar of a region.
 *
 * Satellites of one instrument are merged per cell-day (Terra+Aqua,
 * S-NPP+NOAA-20+NOAA-21) so that the number of platforms flying in a given
 * year does not inflate the series. A cell-day's FRP is the strongest
 * single-platform view of that cell on that day.
 */
class BuildCalendar
{
    public const int CHUNK = 500;

    public function __construct(private readonly CoverageDays $coverageDays) {}

    /**
     * @return int number of calendar entries written
     */
    public function __invoke(Region $region, CalendarPeriod $period): int
    {
        $driver = DB::connection()->getDriverName();
        $periodSql = PeriodExpression::sql($period, $driver);
        $box = $region->bbox();

        $cellDays = DB::table('cell_days')
            ->selectRaw('instrument, cell_id, acq_date, sum(detections) as detections, max(frp_sum) as frp, max(frp_max) as frp_max, sum(footprint_km2) as footprint_km2, sum(day_detections) as day_detections, sum(night_detections) as night_detections, sum(low_confidence) as low_confidence, sum(nominal_confidence) as nominal_confidence, sum(high_confidence) as high_confidence')
            ->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east])
            ->groupBy('instrument', 'cell_id', 'acq_date');

        $periodStart = new Expression("{$periodSql} as period_start");
        $periodGroup = new Expression($periodSql);

        $activity = DB::query()
            ->fromSub($cellDays, 'cd')
            ->select(['instrument', $periodStart])
            ->selectRaw('count(*) as cell_days, count(distinct cell_id) as active_cells, count(distinct acq_date) as active_days, sum(detections) as detections, sum(frp) as frp_sum, max(frp_max) as frp_max, sum(footprint_km2) as footprint_km2, sum(day_detections) as day_detections, sum(night_detections) as night_detections, sum(low_confidence) as low_confidence, sum(nominal_confidence) as nominal_confidence, sum(high_confidence) as high_confidence')
            ->groupBy('instrument', $periodGroup)
            ->get()
            ->keyBy(fn (object $row) => $row->instrument.'|'.substr((string) $row->period_start, 0, 10));

        $binExpression = new Expression(FrpHistogram::SQL_BIN_CASE);

        $histograms = DB::query()
            ->fromSub($cellDays, 'cd')
            ->select(['instrument', $periodStart, new Expression(FrpHistogram::SQL_BIN_CASE.' as bin')])
            ->selectRaw('count(*) as cell_days')
            ->groupBy('instrument', $periodGroup, $binExpression)
            ->get()
            ->groupBy(fn (object $row) => $row->instrument.'|'.substr((string) $row->period_start, 0, 10));

        // Coverage counts the days each instrument was really observed over
        // this region (see CoverageDays), so a period is only emitted, and a
        // zero only means "no fire", when we actually looked.
        $coverage = [];

        foreach (($this->coverageDays)($region) as $instrument => $dates) {
            foreach (array_keys($dates) as $date) {
                $start = $period->startOf(CarbonImmutable::parse($date))->toDateString();
                $coverage[$instrument.'|'.$start] = ($coverage[$instrument.'|'.$start] ?? 0) + 1;
            }
        }

        ksort($coverage);

        $now = now();
        $rows = [];

        foreach ($coverage as $key => $coverageDays) {
            [$instrument, $periodStart] = explode('|', $key, 2);
            $row = $activity->get($key);
            $histogram = FrpHistogram::empty();

            foreach ($histograms->get($key, collect()) as $bin) {
                $histogram[(int) $bin->bin] = (int) $bin->cell_days;
            }

            $rows[] = [
                'region_id' => $region->id,
                'period' => $period->value,
                'period_start' => $periodStart,
                'instrument' => $instrument,
                'detections' => (int) ($row->detections ?? 0),
                'active_cells' => (int) ($row->active_cells ?? 0),
                'cell_days' => (int) ($row->cell_days ?? 0),
                'frp_sum' => round((float) ($row->frp_sum ?? 0), 2),
                'frp_max' => round((float) ($row->frp_max ?? 0), 2),
                'footprint_km2' => round((float) ($row->footprint_km2 ?? 0), 3),
                'day_detections' => (int) ($row->day_detections ?? 0),
                'night_detections' => (int) ($row->night_detections ?? 0),
                'low_confidence' => (int) ($row->low_confidence ?? 0),
                'nominal_confidence' => (int) ($row->nominal_confidence ?? 0),
                'high_confidence' => (int) ($row->high_confidence ?? 0),
                'active_days' => (int) ($row->active_days ?? 0),
                'coverage_days' => $coverageDays,
                'frp_histogram' => json_encode($histogram),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($region, $period, $rows): void {
            CalendarEntry::query()
                ->where('region_id', $region->id)
                ->where('period', $period->value)
                ->delete();

            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                CalendarEntry::query()->insert($chunk);
            }
        });

        return count($rows);
    }
}
