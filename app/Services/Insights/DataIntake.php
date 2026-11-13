<?php

namespace App\Services\Insights;

use App\Enums\FirmsSource;
use App\Enums\IngestStatus;
use App\Enums\Instrument;
use App\Enums\PublicFeed;
use App\Models\IngestRun;
use App\Models\Region;
use App\Services\Firms\BackfillPlanner;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * How much NASA FIRMS data the public (preset) areas have pulled so far and
 * what is still arriving: per-area totals, history coverage, live backfill
 * progress, the shared public feeds and the latest ingest runs.
 */
class DataIntake
{
    public const string CACHE_KEY = 'continuum.data-intake';

    public const int CACHE_SECONDS = 15;

    public const int RECENT_RUNS = 10;

    /** @var Collection<int, IngestRun>|null */
    private ?Collection $areaRuns = null;

    public function __construct(private readonly BackfillPlanner $planner) {}

    /**
     * @return array<string, mixed>
     */
    public function forPresets(): array
    {
        // Polled every 20 s by every open dashboard: serve the cached copy and
        // refresh it after the response once it is older than CACHE_SECONDS.
        return Cache::flexible(self::cacheKey(), [self::CACHE_SECONDS, 3600], fn (): array => $this->build());
    }

    /** Labels inside are translated, so each language keeps its own copy. */
    public static function cacheKey(): string
    {
        return self::CACHE_KEY.'.'.app()->getLocale();
    }

    public function refresh(): void
    {
        Cache::putMany([
            self::cacheKey() => $this->build(),
            Repository::FLEXIBLE_CREATED_KEY_PREFIX.self::cacheKey() => now()->getTimestamp(),
        ], 3600);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $regions = Region::query()->preset()->orderBy('name')->get();
        $this->areaRuns = $this->planner->completedAreaRuns();
        /** @var list<int> $regionIds */
        $regionIds = $regions->pluck('id')->values()->all();

        $stats = IngestRun::query()
            ->whereIn('region_id', $regionIds)
            ->select('region_id')
            ->selectRaw("count(*) filter (where status = 'completed') as completed")
            ->selectRaw("count(*) filter (where status = 'failed') as failed")
            ->selectRaw("count(*) filter (where status = 'running') as running")
            ->selectRaw('coalesce(sum(rows_read), 0) as rows_read')
            ->selectRaw('coalesce(sum(rows_inserted), 0) as rows_inserted')
            ->selectRaw('coalesce(sum(rows_skipped), 0) as rows_skipped')
            ->selectRaw("coalesce(sum(rows_inserted) filter (where instrument = 'modis'), 0) as modis_rows")
            ->selectRaw("coalesce(sum(rows_inserted) filter (where instrument = 'viirs'), 0) as viirs_rows")
            ->selectRaw('min(date_from) as first_date')
            ->selectRaw('max(date_to) as last_date')
            ->selectRaw('max(started_at) as last_started_at')
            ->selectRaw('max(finished_at) as last_finished_at')
            ->groupBy('region_id')
            ->toBase()
            ->get()
            ->keyBy('region_id');

        // Latest failure per area: Postgres DISTINCT ON keeps the first row of
        // each region in the given order.
        $lastErrors = IngestRun::query()
            ->whereIn('region_id', $regionIds)
            ->where('status', IngestStatus::Failed->value)
            ->selectRaw('distinct on (region_id) region_id, error')
            ->orderBy('region_id')
            ->orderByDesc('started_at')
            ->toBase()
            ->pluck('error', 'region_id');

        $batches = $this->planner->activeBatches($regions);

        $areas = array_values($regions->map(fn (Region $region): array => $this->area(
            $region,
            $stats->get($region->id),
            $lastErrors->get($region->id),
            $batches[$region->id] ?? null,
        ))->all());
        $public = $this->publicFeeds();
        $recent = $this->recentRuns($regionIds);

        $running = IngestRun::query()
            ->where('status', IngestStatus::Running->value)
            ->where(fn ($query) => $query->whereIn('region_id', $regionIds)->orWhere('source', 'like', 'public:%'))
            ->count();

        $lastActivity = IngestRun::query()
            ->where(fn ($query) => $query->whereIn('region_id', $regionIds)->orWhere('source', 'like', 'public:%'))
            ->max('started_at');

        $rows = array_sum(array_column($areas, 'rows_inserted')) + $public['rows_inserted'];

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'totals' => [
                'areas' => count($areas),
                'rows_inserted' => $rows,
                'modis_rows' => array_sum(array_map(fn (array $area) => $area['by_instrument']['modis'], $areas)) + $public['by_instrument']['modis'],
                'viirs_rows' => array_sum(array_map(fn (array $area) => $area['by_instrument']['viirs'], $areas)) + $public['by_instrument']['viirs'],
                'active_backfills' => count(array_filter($areas, fn (array $area) => $area['backfill'] !== null)),
                'running_runs' => $running,
                'failed_runs' => array_sum(array_column(array_column($areas, 'runs'), 'failed')) + $public['runs']['failed'],
                'history_percent' => $this->weightedHistory($areas),
                'last_activity_at' => $this->iso($lastActivity),
            ],
            'public_feed' => $public,
            'areas' => $areas,
            'recent_runs' => $recent,
        ];
    }

    /**
     * @param  array{id: string, total: int, done: int, failed: int, started_at: string}|null  $backfill
     * @return array<string, mixed>
     */
    private function area(Region $region, ?object $stats, ?string $lastError, ?array $backfill): array
    {
        if ($backfill !== null) {
            $backfill['percent'] = $backfill['total'] > 0 ? round(100 * $backfill['done'] / $backfill['total'], 1) : 0.0;
        }

        return [
            'id' => $region->id,
            'slug' => $region->slug,
            'name' => $region->name,
            'color' => $region->color,
            'area_km2' => round($region->areaKm2()),
            'rows_read' => (int) ($stats->rows_read ?? 0),
            'rows_inserted' => (int) ($stats->rows_inserted ?? 0),
            'rows_skipped' => (int) ($stats->rows_skipped ?? 0),
            'by_instrument' => [
                'modis' => (int) ($stats->modis_rows ?? 0),
                'viirs' => (int) ($stats->viirs_rows ?? 0),
            ],
            'runs' => [
                'completed' => (int) ($stats->completed ?? 0),
                'failed' => (int) ($stats->failed ?? 0),
                'running' => (int) ($stats->running ?? 0),
            ],
            'first_date' => $this->date($stats->first_date ?? null),
            'last_date' => $this->date($stats->last_date ?? null),
            'last_run_at' => $this->iso($stats->last_started_at ?? null),
            'last_finished_at' => $this->iso($stats->last_finished_at ?? null),
            'last_error' => $lastError === null ? null : mb_substr($lastError, 0, 200),
            'harmonized_at' => $region->harmonized_at?->toIso8601String(),
            'history' => $this->history($region),
            'backfill' => $backfill,
            'links' => [
                'web' => route('regions.show', $region),
            ],
        ];
    }

    /**
     * Share of the full FIRMS history (every archive window plus the recent
     * NRT tail) that has already been fetched for this area.
     *
     * @return array{windows_total: int, windows_done: int, percent: float, earliest_possible: string}
     */
    private function history(Region $region): array
    {
        $progress = $this->planner->historyProgress($region, $this->planner->coverage($region->bbox(), $this->areaRuns));

        return [
            'windows_total' => $progress['total'],
            'windows_done' => $progress['done'],
            'percent' => $progress['total'] > 0 ? round(100 * $progress['done'] / $progress['total'], 1) : 0.0,
            'earliest_possible' => $this->earliestPossible(),
        ];
    }

    private function earliestPossible(): string
    {
        $earliest = null;

        foreach (FirmsSource::archiveSources() as $source) {
            $date = $source->earliestDate();
            $earliest = $earliest === null || $date < $earliest ? $date : $earliest;
        }

        return $earliest ?? FirmsSource::ModisSp->earliestDate();
    }

    /**
     * The key-free global feeds that top up every area daily.
     *
     * @return array<string, mixed>
     */
    private function publicFeeds(): array
    {
        $stats = IngestRun::query()
            ->where('source', 'like', 'public:%')
            ->selectRaw("count(*) filter (where status = 'completed') as completed")
            ->selectRaw("count(*) filter (where status = 'failed') as failed")
            ->selectRaw("count(*) filter (where status = 'running') as running")
            ->selectRaw('coalesce(sum(rows_inserted), 0) as rows_inserted')
            ->selectRaw("coalesce(sum(rows_inserted) filter (where instrument = 'modis'), 0) as modis_rows")
            ->selectRaw("coalesce(sum(rows_inserted) filter (where instrument = 'viirs'), 0) as viirs_rows")
            ->selectRaw('max(started_at) as last_started_at')
            ->first();

        $latest = IngestRun::query()
            ->where('source', 'like', 'public:%')
            ->orderByDesc('started_at')
            ->limit(4 * count(PublicFeed::cases()))
            ->get()
            ->unique(fn (IngestRun $run) => explode(':', $run->source)[1] ?? $run->source)
            ->map(fn (IngestRun $run): array => [
                'feed' => $this->feedLabel($run->source),
                'status' => $run->status->value,
                'rows_inserted' => $run->rows_inserted,
                'started_at' => $run->started_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'rows_inserted' => (int) ($stats->rows_inserted ?? 0),
            'by_instrument' => [
                'modis' => (int) ($stats->modis_rows ?? 0),
                'viirs' => (int) ($stats->viirs_rows ?? 0),
            ],
            'runs' => [
                'completed' => (int) ($stats->completed ?? 0),
                'failed' => (int) ($stats->failed ?? 0),
                'running' => (int) ($stats->running ?? 0),
            ],
            'last_run_at' => $this->iso($stats->last_started_at ?? null),
            'next_run_at' => $this->nextRun((string) config('continuum.schedule.ingest_public_cron', '5 * * * *')),
            'window' => (string) config('continuum.schedule.ingest_public_window', '24h'),
            'latest' => $latest,
        ];
    }

    /**
     * @param  list<int>  $regionIds
     * @return list<array<string, mixed>>
     */
    private function recentRuns(array $regionIds): array
    {
        $runs = IngestRun::query()
            ->with('region:id,slug,name,color')
            ->where(fn ($query) => $query->whereIn('region_id', $regionIds)->orWhere('source', 'like', 'public:%'))
            ->orderByDesc('started_at')
            ->limit(self::RECENT_RUNS)
            ->get()
            ->values()
            ->map(fn (IngestRun $run): array => [
                'id' => $run->id,
                'source' => $run->source,
                'label' => $this->sourceLabel($run->source),
                'instrument' => $run->instrument?->value,
                'region' => $run->region === null ? null : [
                    'slug' => $run->region->slug,
                    'name' => $run->region->name,
                    'color' => $run->region->color,
                ],
                'status' => $run->status->value,
                'rows_read' => $run->rows_read,
                'rows_inserted' => $run->rows_inserted,
                'rows_skipped' => $run->rows_skipped,
                'date_from' => $run->date_from?->toDateString(),
                'date_to' => $run->date_to?->toDateString(),
                'started_at' => $run->started_at->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'duration_s' => $run->finished_at === null ? null : max(0, (int) $run->started_at->diffInSeconds($run->finished_at)),
                'error' => $run->error === null ? null : mb_substr($run->error, 0, 200),
            ])
            ->all();

        return array_values($runs);
    }

    /**
     * Human label for an ingest run source key.
     */
    public function sourceLabel(string $source): string
    {
        [$kind, $rest] = array_pad(explode(':', $source, 2), 2, '');

        return match ($kind) {
            'area' => (str_ends_with($rest, '_SP') ? __('FIRMS archive') : __('FIRMS near-real-time')).' · '.$this->firmsSourceLabel($rest),
            'sample' => __('Bundled sample').' · '.$this->sampleLabel($rest),
            'public' => $this->feedLabel($source),
            'file' => __('CSV import'),
            default => $source,
        };
    }

    private function feedLabel(string $source): string
    {
        $parts = explode(':', $source);
        $feed = PublicFeed::tryFrom($parts[1] ?? '');
        $window = $parts[2] ?? null;

        return __('Public feed').' · '.($feed?->label() ?? ($parts[1] ?? $source)).($window === null ? '' : " · {$window}");
    }

    private function firmsSourceLabel(string $value): string
    {
        return match (FirmsSource::tryFrom($value)) {
            FirmsSource::ModisNrt, FirmsSource::ModisSp => 'MODIS',
            FirmsSource::ViirsSnppNrt, FirmsSource::ViirsSnppSp => 'VIIRS SNPP',
            FirmsSource::ViirsNoaa20Nrt, FirmsSource::ViirsNoaa20Sp => 'VIIRS NOAA-20',
            FirmsSource::ViirsNoaa21Nrt => 'VIIRS NOAA-21',
            null => $value,
        };
    }

    private function sampleLabel(string $rest): string
    {
        $sensor = str_contains($rest, '__') ? substr($rest, strrpos($rest, '__') + 2) : $rest;

        return match ($sensor) {
            'modis' => Instrument::Modis->label(),
            'viirs-snpp' => 'VIIRS SNPP',
            'viirs-noaa20' => 'VIIRS NOAA-20',
            'viirs-noaa21' => 'VIIRS NOAA-21',
            default => $sensor,
        };
    }

    /**
     * Next time a scheduled cron expression fires (UTC), as in routes/console.php.
     */
    private function nextRun(string $cron): string
    {
        return CarbonImmutable::instance((new CronExpression($cron))->getNextRunDate(now('UTC')))->toIso8601String();
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     */
    private function weightedHistory(array $areas): float
    {
        $total = array_sum(array_map(fn (array $area) => $area['history']['windows_total'], $areas));
        $done = array_sum(array_map(fn (array $area) => $area['history']['windows_done'], $areas));

        return $total > 0 ? round(100 * $done / $total, 1) : 0.0;
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    private function date(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 10);
    }
}
