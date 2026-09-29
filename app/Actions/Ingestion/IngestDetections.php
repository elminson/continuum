<?php

namespace App\Actions\Ingestion;

use App\Data\DetectionData;
use App\Data\IngestSummary;
use App\Models\Detection;
use App\Models\IngestRun;
use App\Support\Geo\Grid;
use App\Support\Geo\LatLonGrid;
use Throwable;

/**
 * Writes parsed detections in batches. Existing fingerprints are left
 * untouched, so re-ingesting overlapping feeds is idempotent.
 */
class IngestDetections
{
    public const int CHUNK = 1000;

    /**
     * @param  iterable<DetectionData>  $detections
     */
    public function __invoke(iterable $detections, IngestRun $run, int $rowsSkippedByParser = 0, ?callable $onProgress = null): IngestSummary
    {
        $grid = Grid::fromConfig();
        $calendarGrid = LatLonGrid::fromConfig();

        $buffer = [];
        $read = 0;
        $inserted = 0;
        $dateFrom = null;
        $dateTo = null;

        try {
            foreach ($detections as $detection) {
                $read++;
                $date = $detection->acquiredAt->toDateString();
                $dateFrom = $dateFrom === null || $date < $dateFrom ? $date : $dateFrom;
                $dateTo = $dateTo === null || $date > $dateTo ? $date : $dateTo;

                $buffer[$detection->fingerprint()] = $detection->toRow($grid, $calendarGrid, $run->id);

                if (count($buffer) >= self::CHUNK) {
                    $inserted += $this->flush($buffer);
                    $buffer = [];

                    if ($onProgress !== null) {
                        $onProgress($read, $inserted);
                    }
                }
            }

            if ($buffer !== []) {
                $inserted += $this->flush($buffer);
            }
        } catch (Throwable $exception) {
            $run->fail($exception->getMessage());

            throw $exception;
        }

        $skipped = $rowsSkippedByParser;
        $run->complete($read + $skipped, $inserted, $skipped, $dateFrom, $dateTo);

        return new IngestSummary($read + $skipped, $inserted, $skipped, $dateFrom, $dateTo);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function flush(array $rows): int
    {
        return Detection::query()->insertOrIgnore(array_values($rows));
    }
}
