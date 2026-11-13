<?php

namespace App\Jobs;

use App\Actions\Ingestion\IngestDetections;
use App\Actions\Ingestion\PurgeNearRealTime;
use App\Enums\FirmsSource;
use App\Exceptions\FirmsException;
use App\Models\IngestRun;
use App\Models\Region;
use App\Services\Firms\AreaApiClient;
use App\Services\Firms\CsvParser;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;

/**
 * Fetches one FIRMS Area API window (up to five days) for a region.
 */
#[Backoff([60, 300, 900])]
#[Timeout(600)]
class BackfillRegionWindowJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    /**
     * Genuine failures (bad responses, database errors) allowed before the
     * job is marked failed. Rate-limit pauses release the job without
     * throwing, so they never count.
     */
    public int $maxExceptions = 8;

    /** @var list<int> */
    public const array EARLY_REFRESH_POINTS = [20, 75, 150];

    public const int REFRESH_EVERY = 300;

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $fetchBox  box to request from FIRMS; defaults to the region's own box
     */
    public function __construct(
        public int $regionId,
        public FirmsSource $source,
        public string $from,
        public int $days = AreaApiClient::MAX_DAYS,
        public ?array $fetchBox = null,
    ) {}

    /**
     * Keep retrying for days rather than a fixed number of attempts, because a
     * long backfill can be paused by the FIRMS budget many times over.
     */
    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(3);
    }

    public function handle(AreaApiClient $client, CsvParser $parser, IngestDetections $ingest, PurgeNearRealTime $purge): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $region = Region::query()->findOrFail($this->regionId);
        // Jobs queued before fetchBox existed unserialize without it, so read it defensively.
        $box = isset($this->fetchBox) ? BoundingBox::fromArray($this->fetchBox) : $region->bbox();
        $to = CarbonImmutable::parse($this->from)->addDays($this->days - 1)->toDateString();

        try {
            $csv = $client->fetchCsv($this->source, $box, $this->days, $this->from);
        } catch (FirmsException $exception) {
            if (! $exception->rateLimited) {
                throw $exception;
            }

            // Budget exhausted: wait for the window to reset; the release does not count as an exception.
            $this->release($client->budgetResetsIn() + random_int(5, 45));

            return;
        }

        // Standard-processing data lags NRT by months; only replace NRT rows
        // when FIRMS actually returned science-quality rows for this window.
        if ($this->source->isStandardProcessing() && substr_count(trim($csv), "\n") > 0) {
            $purge($this->source->instrument(), $this->from, $to, $box);
        }

        $run = IngestRun::start("area:{$this->source->value}", $this->source->instrument(), $region, [
            'from' => $this->from,
            'days' => $this->days,
            'bbox' => $box->toArray(),
        ]);

        $ingest($parser->parseString($csv, $box), $run, $parser->rowsSkipped);

        // Re-harmonizing a whole region can take far longer than one window,
        // so it runs as its own (unique) job instead of inside this one.
        if ($this->reachedRefreshMilestone()) {
            HarmonizeRegionJob::dispatch($region->id);
        }
    }

    /**
     * Refresh the region's calendar early in the batch and then every few
     * hundred windows, so the page fills in while the history is still
     * arriving instead of only when the last window lands.
     */
    private function reachedRefreshMilestone(): bool
    {
        $batch = $this->batch();

        if ($batch === null) {
            return false;
        }

        $processed = $batch->processedJobs() + 1;

        return in_array($processed, self::EARLY_REFRESH_POINTS, true) || $processed % self::REFRESH_EVERY === 0;
    }
}
