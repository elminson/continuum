<?php

namespace App\Console\Commands;

use App\Actions\Harmonization\RefreshRegion;
use App\Actions\Ingestion\IngestDetections;
use App\Actions\Ingestion\PurgeNearRealTime;
use App\Enums\FirmsSource;
use App\Models\Region;
use App\Services\Firms\AreaApiClient;
use App\Services\Firms\BackfillPlanner;
use App\Services\Firms\CsvParser;
use Illuminate\Console\Command;

class BackfillRegionCommand extends Command
{
    protected $signature = 'firms:backfill
        {region : Region slug}
        {--source=* : FIRMS sources (default: MODIS_SP, VIIRS_SNPP_SP, VIIRS_NOAA20_SP)}
        {--from= : First date (default: each source\'s earliest date)}
        {--to= : Last date (default: yesterday)}
        {--sync : Run the windows now instead of queueing them}
        {--force : Queue even if a backfill batch for this region is still running}';

    protected $description = 'Backfill a region from the FIRMS Area API (requires FIRMS_MAP_KEY), five days per request';

    public function handle(AreaApiClient $client, BackfillPlanner $planner): int
    {
        if (! $client->hasKey()) {
            $this->error('FIRMS_MAP_KEY is not set. Request a free key at https://firms.modaps.eosdis.nasa.gov/api/map_key/');

            return self::FAILURE;
        }

        $region = Region::query()->where('slug', $this->argument('region'))->first();

        if ($region === null) {
            $this->error('Unknown region slug.');

            return self::INVALID;
        }

        if (! $this->option('sync') && ! $this->option('force') && ($active = $planner->activeBatch($region)) !== null) {
            $this->warn(sprintf(
                'A backfill is already running for %s (batch %s, %d of %d windows done%s). Wait for it or pass --force to queue another.',
                $region->name,
                $active['id'],
                $active['done'],
                $active['total'],
                $active['failed'] > 0 ? ", {$active['failed']} failed" : '',
            ));

            return self::FAILURE;
        }

        $sources = $this->option('source') === []
            ? FirmsSource::archiveSources()
            : array_values(array_map(fn ($source) => FirmsSource::from(strtoupper((string) $source)), (array) $this->option('source')));

        $jobs = $planner->windows(
            $region,
            $sources,
            $this->option('from') ? (string) $this->option('from') : null,
            $this->option('to') ? (string) $this->option('to') : null,
        );

        $this->components->info(sprintf('%s: %d Area API requests across %d source(s)', $region->name, count($jobs), count($sources)));

        if ($this->option('sync')) {
            $bar = $this->output->createProgressBar(count($jobs));

            foreach ($jobs as $job) {
                $job->handle($client, app(CsvParser::class), app(IngestDetections::class), app(PurgeNearRealTime::class), app(RefreshRegion::class));
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->components->info('Backfill complete. Run: php artisan continuum:harmonize '.$region->slug);

            return self::SUCCESS;
        }

        $batch = $planner->dispatch($region, $jobs);

        $this->components->info("Queued batch {$batch->id}. Harmonization runs automatically when it finishes. Start a worker with: php artisan queue:work");

        return self::SUCCESS;
    }
}
