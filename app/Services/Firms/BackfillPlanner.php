<?php

namespace App\Services\Firms;

use App\Enums\FirmsSource;
use App\Enums\IngestStatus;
use App\Jobs\BackfillRegionWindowJob;
use App\Jobs\HarmonizeRegionJob;
use App\Models\IngestRun;
use App\Models\Region;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns a region into the list of FIRMS Area API windows needed to fetch
 * its history, and queues them as one batch followed by harmonization.
 */
class BackfillPlanner
{
    /**
     * Queue that workers drain before the default one, so a newly added area's
     * most recent windows (and every calendar refresh) run ahead of older,
     * still-running backfills.
     */
    public const string PRIORITY_QUEUE = 'priority';

    public const int PRIORITY_WINDOWS = 150;

    /**
     * @param  list<FirmsSource>|null  $sources
     * @return list<BackfillRegionWindowJob>
     */
    public function windows(Region $region, ?array $sources = null, ?string $from = null, ?string $to = null, bool $skipCovered = true): array
    {
        $sources ??= FirmsSource::archiveSources();
        $end = CarbonImmutable::parse($to ?? CarbonImmutable::yesterday()->toDateString());
        $fetchBox = $region->bbox()->padded((float) config('continuum.firms.fetch_padding_deg', 0));
        $covered = $skipCovered ? $this->coverage($region->bbox()) : [];
        $jobs = [];

        foreach ($sources as $source) {
            $start = CarbonImmutable::parse($from ?? $source->earliestDate());

            for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDays(AreaApiClient::MAX_DAYS)) {
                $days = min(AreaApiClient::MAX_DAYS, (int) $cursor->diffInDays($end) + 1);

                if (isset($covered[$source->value.'|'.$cursor->toDateString().'|'.$days])) {
                    continue;
                }

                $jobs[] = new BackfillRegionWindowJob($region->id, $source, $cursor->toDateString(), $days, $fetchBox->toArray());
            }
        }

        return $jobs;
    }

    /**
     * The "SOURCE|from|days" key of every window windows() would plan, without
     * building job objects: the intake panel counts thousands of these per poll.
     *
     * @param  list<FirmsSource>  $sources
     * @return list<string>
     */
    public function windowKeys(array $sources, ?string $from = null, ?string $to = null): array
    {
        $end = CarbonImmutable::parse($to ?? CarbonImmutable::yesterday()->toDateString());
        $keys = [];

        foreach ($sources as $source) {
            $start = CarbonImmutable::parse($from ?? $source->earliestDate());

            for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDays(AreaApiClient::MAX_DAYS)) {
                $days = min(AreaApiClient::MAX_DAYS, (int) $cursor->diffInDays($end) + 1);
                $keys[] = $source->value.'|'.$cursor->toDateString().'|'.$days;
            }
        }

        return $keys;
    }

    /**
     * How many of a region's full-history windows are still to fetch.
     *
     * @param  array<string, true>|null  $covered  precomputed coverage() for the region's box
     * @return array{total: int, done: int}
     */
    public function historyProgress(Region $region, ?array $covered = null): array
    {
        $yesterday = CarbonImmutable::yesterday();
        $tailStart = $yesterday->subDays((int) config('continuum.firms.nrt_tail_days', 70));
        $covered ??= $this->coverage($region->bbox());

        $keys = [
            ...$this->windowKeys(FirmsSource::nrtSources(), $tailStart->toDateString(), $yesterday->toDateString()),
            ...$this->windowKeys(FirmsSource::archiveSources(), null, $yesterday->toDateString()),
        ];

        $done = 0;

        foreach ($keys as $key) {
            if (isset($covered[$key])) {
                $done++;
            }
        }

        return ['total' => count($keys), 'done' => $done];
    }

    /**
     * Windows already fetched for a box that fully contains the given one,
     * keyed "SOURCE|from|days". FIRMS charges per day requested, not per
     * area, so any earlier fetch of a larger box makes the window free.
     *
     * @param  Collection<int, IngestRun>|null  $runs  preloaded completedAreaRuns()
     * @return array<string, true>
     */
    public function coverage(BoundingBox $box, ?Collection $runs = null): array
    {
        $covered = [];

        $runs ??= $this->completedAreaRuns();

        foreach ($runs as $run) {
            $meta = $run->meta ?? [];

            if (! isset($meta['bbox'], $meta['from'], $meta['days'])) {
                continue;
            }

            try {
                $fetched = BoundingBox::fromArray($meta['bbox']);
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($fetched->containsBox($box)) {
                $covered[substr($run->source, 5).'|'.$meta['from'].'|'.(int) $meta['days']] = true;
            }
        }

        return $covered;
    }

    /**
     * Completed Area API runs with the box they fetched, for coverage().
     *
     * @return Collection<int, IngestRun>
     */
    public function completedAreaRuns(): Collection
    {
        return IngestRun::query()
            ->where('source', 'like', 'area:%')
            ->where('status', IngestStatus::Completed->value)
            ->whereNotNull('meta->bbox')
            ->get(['source', 'meta']);
    }

    /**
     * Everything FIRMS can give for a region: the standard-processing archive
     * of every instrument plus the near-real-time tail the archive has not
     * reached yet. Newest windows come first so the calendar fills from today
     * backwards.
     *
     * @return list<BackfillRegionWindowJob>
     */
    public function fullHistory(Region $region): array
    {
        $yesterday = CarbonImmutable::yesterday();
        $tailStart = $yesterday->subDays((int) config('continuum.firms.nrt_tail_days', 70));

        $jobs = [
            ...$this->windows($region, FirmsSource::nrtSources(), $tailStart->toDateString(), $yesterday->toDateString()),
            ...$this->windows($region, FirmsSource::archiveSources(), null, $yesterday->toDateString()),
        ];

        usort($jobs, fn (BackfillRegionWindowJob $a, BackfillRegionWindowJob $b) => strcmp($b->from, $a->from));

        return $jobs;
    }

    /**
     * @param  list<BackfillRegionWindowJob>  $jobs
     */
    public function dispatch(Region $region, array $jobs): Batch
    {
        $regionId = $region->id;

        foreach (array_slice($jobs, 0, self::PRIORITY_WINDOWS) as $job) {
            $job->onQueue(self::PRIORITY_QUEUE);
        }

        return Bus::batch($jobs)
            ->name(self::batchName($region))
            ->allowFailures()
            ->finally(function () use ($regionId): void {
                HarmonizeRegionJob::dispatch($regionId);
            })
            ->dispatch();
    }

    /**
     * Progress of the region's unfinished backfill batch, if any.
     *
     * @return array{id: string, total: int, done: int, failed: int, started_at: string}|null
     */
    public function activeBatch(Region $region): ?array
    {
        $batch = DB::table('job_batches')
            ->where('name', self::batchName($region))
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->orderByDesc('created_at')
            ->first();

        if ($batch === null) {
            return null;
        }

        return [
            'id' => (string) $batch->id,
            'total' => (int) $batch->total_jobs,
            'done' => (int) $batch->total_jobs - (int) $batch->pending_jobs,
            'failed' => (int) $batch->failed_jobs,
            'started_at' => CarbonImmutable::createFromTimestamp((int) $batch->created_at)->toIso8601String(),
        ];
    }

    public static function batchName(Region $region): string
    {
        return "backfill:{$region->slug}";
    }
}
