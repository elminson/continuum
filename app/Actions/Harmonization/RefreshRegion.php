<?php

namespace App\Actions\Harmonization;

use App\Enums\IngestStatus;
use App\Models\IngestRun;
use App\Models\Region;

/**
 * Bring a region's calendar up to date with whatever detections exist for
 * it: aggregate the days that changed since the last refresh, then rebuild
 * calendars, calibration and anomaly screening.
 */
class RefreshRegion
{
    public function __construct(
        private readonly AggregateCellDays $aggregate,
        private readonly HarmonizeRegion $harmonize,
    ) {}

    /**
     * @return array{entries: array<string, int>, calibration: array<int, array<string, mixed>>, alerts: array<string, int>}
     */
    public function __invoke(Region $region, bool $aggregate = true): array
    {
        if ($aggregate) {
            [$from, $to] = $this->datesToAggregate($region);

            if ($from !== null || $to !== null || $region->harmonized_at === null) {
                ($this->aggregate)($from, $to, $region->bbox());
            }
        }

        return ($this->harmonize)($region);
    }

    /**
     * Date range that needs (re)aggregation: everything for a region that was
     * never refreshed, otherwise only the dates covered by ingest runs that
     * completed since the last refresh. Returns [null, null] for "everything".
     *
     * @return array{0: string|null, 1: string|null}
     */
    public function datesToAggregate(Region $region): array
    {
        $runs = IngestRun::query()
            ->where('status', IngestStatus::Completed->value)
            ->whereNotNull('date_from');

        if ($region->harmonized_at === null) {
            return [null, null];
        }

        $range = $runs
            ->where('finished_at', '>=', $region->harmonized_at)
            ->selectRaw('min(date_from) as date_from, max(date_to) as date_to')
            ->first();

        if ($range === null || $range->date_from === null) {
            return ['1970-01-01', '1970-01-01'];
        }

        return [substr((string) $range->date_from, 0, 10), substr((string) $range->date_to, 0, 10)];
    }
}
