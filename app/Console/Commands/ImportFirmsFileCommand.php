<?php

namespace App\Console\Commands;

use App\Actions\Ingestion\ImportFirmsFile;
use App\Support\Geo\BoundingBox;
use Illuminate\Console\Command;

class ImportFirmsFileCommand extends Command
{
    protected $signature = 'firms:import
        {path : Path to a FIRMS CSV or CSV.GZ file (public feed, Area API or archive download)}
        {--bbox= : Keep only detections inside west,south,east,north}
        {--source= : Label stored on the ingest run (defaults to file:<name>)}';

    protected $description = 'Import active fire detections from a FIRMS CSV file';

    public function handle(ImportFirmsFile $import): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $clip = $this->option('bbox') ? BoundingBox::fromString((string) $this->option('bbox')) : null;
        $source = (string) ($this->option('source') ?: 'file:'.basename($path));

        $this->components->info("Importing {$path}".($clip ? ' clipped to '.$clip->toFirmsString() : ''));

        $summary = $import($path, $source, $clip, null, function (int $read, int $inserted): void {
            $this->output->write("\r  read {$read} · inserted {$inserted}");
        });

        $this->output->writeln('');
        $this->components->twoColumnDetail('Rows read', number_format($summary->read));
        $this->components->twoColumnDetail('Inserted', number_format($summary->inserted));
        $this->components->twoColumnDetail('Skipped (invalid / outside bbox / static sources)', number_format($summary->skipped));
        $this->components->twoColumnDetail('Already present', number_format($summary->duplicates()));
        $this->components->twoColumnDetail('Dates', ($summary->dateFrom ?? '-').' → '.($summary->dateTo ?? '-'));

        return self::SUCCESS;
    }
}
