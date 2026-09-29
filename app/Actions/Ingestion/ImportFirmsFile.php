<?php

namespace App\Actions\Ingestion;

use App\Data\IngestSummary;
use App\Models\IngestRun;
use App\Models\Region;
use App\Services\Firms\CsvParser;
use App\Support\Geo\BoundingBox;

/**
 * Parses one FIRMS CSV (plain or gzip) and stores its detections.
 */
class ImportFirmsFile
{
    public function __construct(
        private readonly CsvParser $parser,
        private readonly IngestDetections $ingest,
    ) {}

    public function __invoke(string $path, string $source, ?BoundingBox $clip = null, ?Region $region = null, ?callable $onProgress = null): IngestSummary
    {
        $run = IngestRun::start($source, null, $region, [
            'file' => basename($path),
            'bytes' => is_file($path) ? filesize($path) : null,
            'clip' => $clip?->toArray(),
        ]);

        $detections = $this->parser->parseFile($path, $clip);

        $summary = ($this->ingest)($detections, $run, 0, $onProgress);

        if ($this->parser->rowsSkipped > 0) {
            $run->forceFill([
                'rows_read' => $this->parser->rowsRead,
                'rows_skipped' => $this->parser->rowsSkipped,
            ])->save();

            $summary = new IngestSummary($this->parser->rowsRead, $summary->inserted, $this->parser->rowsSkipped, $summary->dateFrom, $summary->dateTo);
        }

        if ($run->instrument === null && $summary->inserted > 0) {
            $instrument = $run->detections()->value('instrument');
            $run->forceFill(['instrument' => $instrument])->save();
        }

        return $summary;
    }
}
