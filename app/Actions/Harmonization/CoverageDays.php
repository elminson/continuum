<?php

namespace App\Actions\Harmonization;

use App\Enums\IngestStatus;
use App\Models\IngestRun;
use App\Models\Region;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The days on which each instrument was actually observed over a region.
 *
 * FIRMS detections are positive-only, so "no detections" only means "no
 * fire" on a day we fetched for that place. A day is covered when either
 *
 *  - a completed ingest run fetched that day for a box containing the
 *    region (Area API windows, bundled samples, clipped or global feeds), or
 *  - the instrument has at least one detection inside the region that day.
 *
 * Days that merely have data somewhere else on Earth are not coverage: they
 * would count as fire-free years and poison every climatology built on them.
 */
class CoverageDays
{
    /**
     * @return array<string, array<string, true>> instrument => set of Y-m-d dates
     */
    public function __invoke(Region $region): array
    {
        $box = $region->bbox();
        $covered = [];

        $runs = IngestRun::query()
            ->where('status', IngestStatus::Completed->value)
            ->whereNotNull('instrument')
            ->get(['id', 'source', 'instrument', 'region_id', 'date_from', 'date_to', 'meta']);

        foreach ($runs as $run) {
            if (! $this->runCovers($run, $region, $box)) {
                continue;
            }

            [$from, $to] = $this->runDates($run);

            if ($from === null || $to === null) {
                continue;
            }

            $instrument = $run->instrument->value;

            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $covered[$instrument][$day->toDateString()] = true;
            }
        }

        $active = DB::table('cell_days')
            ->select(['instrument', 'acq_date'])
            ->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east])
            ->distinct()
            ->get();

        foreach ($active as $row) {
            $covered[(string) $row->instrument][substr((string) $row->acq_date, 0, 10)] = true;
        }

        return $covered;
    }

    private function runCovers(IngestRun $run, Region $region, BoundingBox $box): bool
    {
        $meta = $run->meta ?? [];

        // Area API windows record the box they fetched.
        if (isset($meta['bbox'])) {
            return $this->contains($meta['bbox'], $box);
        }

        // Files and feeds record the clip applied on import; no clip means the whole file was kept.
        if (array_key_exists('clip', $meta)) {
            return $meta['clip'] === null || $this->contains($meta['clip'], $box);
        }

        // Older runs without box metadata only vouch for their own region.
        return $run->region_id === $region->id;
    }

    /**
     * @param  mixed  $bbox
     */
    private function contains($bbox, BoundingBox $box): bool
    {
        if (! is_array($bbox) || count($bbox) !== 4) {
            return false;
        }

        $edges = array_values($bbox);

        foreach ($edges as $edge) {
            if (! is_numeric($edge)) {
                return false;
            }
        }

        try {
            return (new BoundingBox((float) $edges[0], (float) $edges[1], (float) $edges[2], (float) $edges[3]))->containsBox($box);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Area API windows cover every requested day, even fire-free ones at the
     * end; other runs cover the dates their rows span.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function runDates(IngestRun $run): array
    {
        $meta = $run->meta ?? [];

        if (isset($meta['from'], $meta['days']) && (int) $meta['days'] > 0) {
            $from = CarbonImmutable::parse((string) $meta['from']);

            return [$from, $from->addDays((int) $meta['days'] - 1)];
        }

        return [$run->date_from, $run->date_to];
    }
}
