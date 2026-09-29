<?php

namespace App\Actions\Ingestion;

use App\Enums\Instrument;
use App\Models\CellDay;
use App\Models\Detection;
use App\Support\Geo\BoundingBox;

/**
 * Removes NRT/URT/RT detections (and their cell-day aggregates) for a
 * window that is about to be replaced by standard-processing data.
 */
class PurgeNearRealTime
{
    public function __invoke(Instrument $instrument, string $from, string $to, ?BoundingBox $box = null): int
    {
        $deleted = Detection::query()
            ->where('instrument', $instrument->value)
            ->whereBetween('acq_date', [$from, $to])
            ->when($box, fn ($query) => $query->withinBox($box))
            ->where(function ($query): void {
                $query->where('version', 'like', '%NRT')
                    ->orWhere('version', 'like', '%URT')
                    ->orWhere('version', 'like', '%RT');
            })
            ->delete();

        if ($deleted > 0) {
            CellDay::query()
                ->where('instrument', $instrument->value)
                ->whereBetween('acq_date', [$from, $to])
                ->when($box, fn ($query) => $query->withinBox($box))
                ->delete();
        }

        return $deleted;
    }
}
