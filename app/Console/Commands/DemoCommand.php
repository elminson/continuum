<?php

namespace App\Console\Commands;

use App\Actions\Ingestion\ImportFirmsFile;
use App\Models\Alert;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\CellDay;
use App\Models\Detection;
use App\Models\IngestRun;
use App\Models\Region;
use Database\Seeders\RegionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoCommand extends Command
{
    protected $signature = 'continuum:demo
        {--fresh : Remove all detections and derived data first}
        {--region=* : Only load these preset slugs}';

    protected $description = 'Load the bundled real FIRMS samples (four regions, 8 days, 4 sensors) and run the full harmonization pipeline';

    public function handle(ImportFirmsFile $import): int
    {
        $path = rtrim((string) config('continuum.samples_path'), '/');

        if ($this->option('fresh')) {
            $this->components->task('Clearing detections and derived data', function (): void {
                DB::transaction(function (): void {
                    Alert::query()->delete();
                    CalibrationFactor::query()->delete();
                    CalendarEntry::query()->delete();
                    CellDay::query()->delete();
                    Detection::query()->delete();
                    IngestRun::query()->delete();
                });
            });
        }

        $this->components->task('Seeding preset regions', fn () => $this->callSilently('db:seed', ['--class' => RegionSeeder::class, '--force' => true]));

        $regions = Region::query()->preset()
            ->when($this->option('region') !== [], fn ($query) => $query->whereIn('slug', (array) $this->option('region')))
            ->get();

        foreach ($regions as $region) {
            $files = glob("{$path}/{$region->slug}__*.csv.gz") ?: [];

            if ($files === []) {
                $this->components->warn("No bundled sample for {$region->slug}");

                continue;
            }

            foreach ($files as $file) {
                $this->components->task(basename($file), function () use ($import, $file, $region): void {
                    $summary = $import($file, 'sample:'.basename($file, '.csv.gz'), $region->bbox(), $region);
                    $this->newLine();
                    $this->components->twoColumnDetail('  inserted / already present', number_format($summary->inserted).' / '.number_format($summary->duplicates()));
                });
            }
        }

        return $this->call('continuum:harmonize', ['region' => $regions->pluck('slug')->all()]);
    }
}
