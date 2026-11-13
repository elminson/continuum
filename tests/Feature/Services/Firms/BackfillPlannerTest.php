<?php

use App\Actions\Ingestion\IngestDetections;
use App\Actions\Ingestion\PurgeNearRealTime;
use App\Enums\FirmsSource;
use App\Enums\IngestStatus;
use App\Jobs\BackfillRegionWindowJob;
use App\Jobs\HarmonizeRegionJob;
use App\Models\IngestRun;
use App\Models\Region;
use App\Services\Firms\AreaApiClient;
use App\Services\Firms\BackfillPlanner;
use App\Services\Firms\CsvParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-27 12:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('splits a date range into five-day windows per source', function () {
    $region = Region::factory()->preset()->create();

    $jobs = app(BackfillPlanner::class)->windows($region, [FirmsSource::ModisSp], '2026-01-01', '2026-01-12');

    expect($jobs)->toHaveCount(3)
        ->and($jobs[0])->toBeInstanceOf(BackfillRegionWindowJob::class)
        ->and(array_map(fn (BackfillRegionWindowJob $job) => [$job->from, $job->days], $jobs))
        ->toBe([['2026-01-01', 5], ['2026-01-06', 5], ['2026-01-11', 2]]);
});

it('plans the full history newest first with a near-real-time tail', function () {
    config()->set('continuum.firms.nrt_tail_days', 10);
    $region = Region::factory()->preset()->create();

    $jobs = app(BackfillPlanner::class)->fullHistory($region);
    $froms = array_map(fn (BackfillRegionWindowJob $job) => $job->from, $jobs);
    $sources = array_unique(array_map(fn (BackfillRegionWindowJob $job) => $job->source->value, $jobs));

    expect($froms)->toBe(array_reverse(collect($froms)->sort()->values()->all()))
        ->and($froms[0])->toBe('2026-09-26')
        ->and(end($froms))->toBe('2000-11-01')
        ->and($sources)->toContain('MODIS_NRT', 'VIIRS_NOAA21_NRT', 'MODIS_SP', 'VIIRS_SNPP_SP', 'VIIRS_NOAA20_SP')
        ->and($sources)->not->toContain('VIIRS_SNPP_NRT');
});

it('dispatches one named batch that harmonizes the region when finished', function () {
    Bus::fake();
    $region = Region::factory()->preset()->create(['slug' => 'test-area']);
    $planner = app(BackfillPlanner::class);

    $planner->dispatch($region, $planner->windows($region, [FirmsSource::ModisSp], '2026-01-01', '2026-01-05'));

    Bus::assertBatched(fn ($batch) => $batch->name === 'backfill:test-area' && $batch->jobs->count() === 1);
    Bus::assertNotDispatched(HarmonizeRegionJob::class);
});

it('puts the newest windows on the priority queue and the rest on the default queue', function () {
    Bus::fake();
    config()->set('continuum.firms.nrt_tail_days', 1);
    $region = Region::factory()->preset()->create();
    $planner = app(BackfillPlanner::class);

    $planner->dispatch($region, $planner->fullHistory($region));

    Bus::assertBatched(function ($batch) {
        $queues = $batch->jobs->map(fn (BackfillRegionWindowJob $job) => $job->queue ?? 'default');

        return $queues->take(BackfillPlanner::PRIORITY_WINDOWS)->every(fn ($queue) => $queue === BackfillPlanner::PRIORITY_QUEUE)
            && $queues->slice(BackfillPlanner::PRIORITY_WINDOWS)->every(fn ($queue) => $queue === 'default');
    });
});

it('reports the progress of an unfinished batch', function () {
    config()->set('queue.default', 'database');
    $region = Region::factory()->preset()->create(['slug' => 'progress-area']);

    expect(app(BackfillPlanner::class)->activeBatch($region))->toBeNull();

    $planner = app(BackfillPlanner::class);
    $planner->dispatch($region, $planner->windows($region, [FirmsSource::ModisSp], '2026-01-01', '2026-01-10'));

    expect(app(BackfillPlanner::class)->activeBatch($region))
        ->toMatchArray(['total' => 2, 'done' => 0, 'failed' => 0]);
});

it('skips windows already fetched for a box that contains the area, and pads the box it requests', function () {
    config()->set('continuum.firms.fetch_padding_deg', 1.0);
    $region = Region::factory()->preset()->withBox([-71, 17, -67, 20])->create();

    IngestRun::factory()->create([
        'source' => 'area:MODIS_SP',
        'status' => IngestStatus::Completed,
        'meta' => ['from' => '2026-01-01', 'days' => 5, 'bbox' => [-80, 10, -60, 25]],
    ]);
    IngestRun::factory()->create([
        'source' => 'area:MODIS_SP',
        'status' => IngestStatus::Completed,
        'meta' => ['from' => '2026-01-06', 'days' => 5, 'bbox' => [-70, 18, -68, 19]],
    ]);

    $jobs = app(BackfillPlanner::class)->windows($region, [FirmsSource::ModisSp], '2026-01-01', '2026-01-12');

    expect(array_map(fn (BackfillRegionWindowJob $job) => $job->from, $jobs))->toBe(['2026-01-06', '2026-01-11'])
        ->and($jobs[0]->fetchBox)->toBe([-72.0, 16.0, -66.0, 21.0]);
});

it('handles window jobs serialized before the fetch box existed', function () {
    $region = Region::factory()->preset()->withBox([-71, 17, -67, 20])->create();
    $job = new BackfillRegionWindowJob($region->id, FirmsSource::ModisSp, '2026-01-01', 5, [-72, 16, -66, 21]);

    // Simulate a payload from the old class shape: no fetchBox property at all.
    $legacy = unserialize(str_replace('s:8:"fetchBox";', 's:9:"fetchBoxX";', serialize($job)));

    expect(isset($legacy->fetchBox))->toBeFalse();

    Http::fake(['*' => Http::response("latitude,longitude,brightness,scan,track,acq_date,acq_time,satellite,confidence,version,bright_t31,frp,daynight\n")]);
    config()->set('continuum.firms.map_key', 'test-key');

    $legacy->handle(
        app(AreaApiClient::class),
        app(CsvParser::class),
        app(IngestDetections::class),
        app(PurgeNearRealTime::class),
    );

    Http::assertSent(fn ($request) => str_contains($request->url(), '/-71,17,-67,20/'));
});

it('reads the unfinished batches of many areas in one query', function () {
    config()->set('queue.default', 'database');
    $planner = app(BackfillPlanner::class);
    $busy = Region::factory()->preset()->create(['slug' => 'busy-area']);
    $idle = Region::factory()->preset()->create(['slug' => 'idle-area']);

    $planner->dispatch($busy, $planner->windows($busy, [FirmsSource::ModisSp], '2026-01-01', '2026-01-10'));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $active = $planner->activeBatches([$busy, $idle]);
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($active)->toHaveKey($busy->id)->not->toHaveKey($idle->id)
        ->and($active[$busy->id])->toMatchArray(['total' => 2, 'done' => 0]);
});

it('hands the milestone re-harmonization to its own job instead of running it inside the window', function () {
    Queue::fake();
    Http::fake(['*' => Http::response("latitude,longitude,brightness,scan,track,acq_date,acq_time,satellite,confidence,version,bright_t31,frp,daynight\n")]);
    config()->set('continuum.firms.map_key', 'test-key');
    $region = Region::factory()->preset()->create();

    // 19 windows done: this one is the 20th, an early refresh milestone.
    [$job] = (new BackfillRegionWindowJob($region->id, FirmsSource::ModisSp, '2026-01-01', 5))
        ->withFakeBatch(totalJobs: 100, pendingJobs: 81);

    $job->handle(app(AreaApiClient::class), app(CsvParser::class), app(IngestDetections::class), app(PurgeNearRealTime::class));

    Queue::assertPushed(HarmonizeRegionJob::class, fn (HarmonizeRegionJob $queued) => $queued->regionId === $region->id);
    expect($region->refresh()->harmonized_at)->toBeNull();
});
