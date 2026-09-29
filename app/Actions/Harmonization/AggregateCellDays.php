<?php

namespace App\Actions\Harmonization;

use App\Models\CellDay;
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
    public const int CHUNK = 1000;

    /**
     * Days aggregated per query. Detections are indexed by acq_date, so a
     * month-sized chunk keeps each query small while avoiding one round trip
     * per day across a 26-year record.
     */
    public const int CHUNK_DAYS = 31;

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

            $rows = $this->filtered(DB::table('detections'), $cursor->toDateString(), $chunkEnd->toDateString(), $box)
                ->selectRaw("cell_id, acq_date, satellite, min(instrument) as instrument, min(calendar_cell_id) as calendar_cell_id, avg(latitude) as latitude, avg(longitude) as longitude, count(*) as detections, sum(case when daynight = 'D' then 1 else 0 end) as day_detections, sum(case when daynight = 'N' then 1 else 0 end) as night_detections, sum(case when confidence = 'low' then 1 else 0 end) as low_confidence, sum(case when confidence = 'nominal' then 1 else 0 end) as nominal_confidence, sum(case when confidence = 'high' then 1 else 0 end) as high_confidence, sum(frp) as frp_sum, max(frp) as frp_max, sum(footprint_km2) as footprint_km2")
                ->groupBy('cell_id', 'acq_date', 'satellite')
                ->get();

            foreach ($rows->chunk(self::CHUNK) as $chunk) {
                $payload = $chunk->map(fn (object $row) => [
                    'cell_id' => (int) $row->cell_id,
                    'acq_date' => substr((string) $row->acq_date, 0, 10),
                    'satellite' => $row->satellite,
                    'instrument' => $row->instrument,
                    'calendar_cell_id' => (int) $row->calendar_cell_id,
                    'latitude' => round((float) $row->latitude, 5),
                    'longitude' => round((float) $row->longitude, 5),
                    'detections' => (int) $row->detections,
                    'day_detections' => (int) $row->day_detections,
                    'night_detections' => (int) $row->night_detections,
                    'low_confidence' => (int) $row->low_confidence,
                    'nominal_confidence' => (int) $row->nominal_confidence,
                    'high_confidence' => (int) $row->high_confidence,
                    'frp_sum' => round((float) $row->frp_sum, 2),
                    'frp_max' => round((float) $row->frp_max, 2),
                    'footprint_km2' => round((float) $row->footprint_km2, 4),
                ])->all();

                CellDay::query()->upsert(
                    $payload,
                    ['cell_id', 'acq_date', 'satellite'],
                    ['instrument', 'calendar_cell_id', 'latitude', 'longitude', 'detections', 'day_detections', 'night_detections', 'low_confidence', 'nominal_confidence', 'high_confidence', 'frp_sum', 'frp_max', 'footprint_km2'],
                );

                $written += count($payload);
            }

            if ($onChunk !== null) {
                $onChunk($cursor->toDateString(), $chunkEnd->toDateString(), $rows->count());
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
