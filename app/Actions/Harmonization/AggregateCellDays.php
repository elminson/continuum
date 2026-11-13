<?php

namespace App\Actions\Harmonization;

use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Collapses raw detections into one row per (grid cell, day, satellite).
 * This is the first harmonization step: it removes the VIIRS sub-pixel
 * multiplicity before anything is counted.
 */
class AggregateCellDays
{
    /**
     * Days aggregated per query. Detections are indexed by acq_date, so a
     * month-sized chunk keeps each query small while avoiding one round trip
     * per day across a 26-year record.
     */
    public const int CHUNK_DAYS = 31;

    /** @var list<string> */
    private const array UPDATED = ['instrument', 'calendar_cell_id', 'latitude', 'longitude', 'detections', 'day_detections', 'night_detections', 'low_confidence', 'nominal_confidence', 'high_confidence', 'frp_sum', 'frp_max', 'footprint_km2'];

    /** @var list<string> */
    private const array COLUMNS = ['cell_id', 'acq_date', 'satellite', ...self::UPDATED];

    /** Same order as COLUMNS. */
    private const string SELECT = "cell_id, acq_date, satellite, min(instrument), min(calendar_cell_id), round(avg(latitude)::numeric, 5), round(avg(longitude)::numeric, 5), count(*), count(*) filter (where daynight = 'D'), count(*) filter (where daynight = 'N'), count(*) filter (where confidence = 'low'), count(*) filter (where confidence = 'nominal'), count(*) filter (where confidence = 'high'), round(coalesce(sum(frp), 0)::numeric, 2), round(coalesce(max(frp), 0)::numeric, 2), round(coalesce(sum(footprint_km2), 0)::numeric, 4)";

    /**
     * @return int number of cell-day rows written
     */
    public function __invoke(?string $from = null, ?string $to = null, ?BoundingBox $box = null, ?callable $onChunk = null): int
    {
        $bounds = $this->filtered(DB::table('detections'), $from, $to, $box)
            ->selectRaw('min(acq_date) as first_date, max(acq_date) as last_date')
            ->first();

        if ($bounds === null || $bounds->first_date === null) {
            return 0;
        }

        $cursor = CarbonImmutable::parse(substr((string) $bounds->first_date, 0, 10));
        $last = CarbonImmutable::parse(substr((string) $bounds->last_date, 0, 10));
        $written = 0;

        while ($cursor->lte($last)) {
            $chunkEnd = $cursor->addDays(self::CHUNK_DAYS - 1)->min($last);

            $select = $this->filtered(DB::table('detections'), $cursor->toDateString(), $chunkEnd->toDateString(), $box)
                ->selectRaw(self::SELECT)
                ->groupBy('cell_id', 'acq_date', 'satellite');

            // Aggregate and upsert inside Postgres: a month of global feeds is
            // millions of groups, far too many to pull into PHP. Rows whose
            // totals did not change are left alone, which keeps re-runs cheap.
            $rows = DB::affectingStatement(
                'insert into cell_days ('.implode(', ', self::COLUMNS).') '.$select->toSql()
                .' on conflict (cell_id, acq_date, satellite) do update set '
                .implode(', ', array_map(fn (string $column) => "{$column} = excluded.{$column}", self::UPDATED))
                .' where ('.implode(', ', array_map(fn (string $column) => "cell_days.{$column}", self::UPDATED)).')'
                .' is distinct from ('.implode(', ', array_map(fn (string $column) => "excluded.{$column}", self::UPDATED)).')',
                $select->getBindings(),
            );

            $written += $rows;

            if ($onChunk !== null) {
                $onChunk($cursor->toDateString(), $chunkEnd->toDateString(), $rows);
            }

            $cursor = $chunkEnd->addDay();
        }

        return $written;
    }

    private function filtered(Builder $query, ?string $from, ?string $to, ?BoundingBox $box): Builder
    {
        if ($from !== null) {
            $query->where('acq_date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('acq_date', '<=', $to);
        }

        if ($box !== null) {
            $query->whereBetween('latitude', [$box->south, $box->north])
                ->whereBetween('longitude', [$box->west, $box->east]);
        }

        return $query;
    }
}
