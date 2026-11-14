<?php

use App\Enums\IngestStatus;
use App\Enums\Instrument;
use App\Models\IngestRun;
use App\Models\Region;
use App\Models\User;
use App\Services\Firms\BackfillPlanner;
use App\Services\Insights\DataIntake;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::flush();
});

it('reports rows pulled, coverage and live backfill progress for public areas only', function () {
    $preset = Region::factory()->preset()->create(['name' => 'Gran Chaco', 'slug' => 'gran-chaco']);
    $private = Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'secret-area']);

    IngestRun::factory()->create([
        'region_id' => $preset->id,
        'source' => 'area:MODIS_SP',
        'instrument' => Instrument::Modis,
        'rows_read' => 120,
        'rows_inserted' => 100,
        'rows_skipped' => 20,
        'date_from' => '2024-08-01',
        'date_to' => '2024-08-05',
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHours(2)->addSeconds(30),
    ]);
    IngestRun::factory()->create([
        'region_id' => $preset->id,
        'source' => 'sample:gran-chaco__viirs-snpp',
        'instrument' => Instrument::Viirs,
        'rows_inserted' => 250,
        'date_from' => '2026-09-18',
        'date_to' => '2026-09-25',
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addSeconds(5),
    ]);
    IngestRun::factory()->create([
        'region_id' => $preset->id,
        'source' => 'area:VIIRS_NOAA20_NRT',
        'instrument' => Instrument::Viirs,
        'status' => IngestStatus::Failed,
        'error' => 'FIRMS returned 503',
        'started_at' => now()->subMinutes(10),
        'finished_at' => now()->subMinutes(10),
    ]);
    IngestRun::factory()->create([
        'region_id' => $private->id,
        'source' => 'area:MODIS_SP',
        'instrument' => Instrument::Modis,
        'rows_inserted' => 9_999,
        'started_at' => now(),
    ]);
    IngestRun::factory()->create([
        'region_id' => null,
        'source' => 'public:viirs-noaa20:48h',
        'instrument' => Instrument::Viirs,
        'rows_inserted' => 40,
        'started_at' => now()->subMinutes(30),
        'finished_at' => now()->subMinutes(29),
    ]);

    DB::table('job_batches')->insert([
        'id' => 'batch-1',
        'name' => BackfillPlanner::batchName($preset),
        'total_jobs' => 200,
        'pending_jobs' => 150,
        'failed_jobs' => 3,
        'failed_job_ids' => '[]',
        'options' => null,
        'cancelled_at' => null,
        'created_at' => now()->subMinutes(20)->getTimestamp(),
        'finished_at' => null,
    ]);

    $intake = app(DataIntake::class)->forPresets();

    expect($intake['areas'])->toHaveCount(1)
        ->and($intake['areas'][0]['slug'])->toBe('gran-chaco')
        ->and($intake['areas'][0]['rows_inserted'])->toBe(350)
        ->and($intake['areas'][0]['rows_read'])->toBe(120)
        ->and($intake['areas'][0]['rows_skipped'])->toBe(20)
        ->and($intake['areas'][0]['by_instrument'])->toBe(['modis' => 100, 'viirs' => 250])
        ->and($intake['areas'][0]['runs'])->toBe(['completed' => 2, 'failed' => 1, 'running' => 0])
        ->and($intake['areas'][0]['first_date'])->toBe('2024-08-01')
        ->and($intake['areas'][0]['last_date'])->toBe('2026-09-25')
        ->and($intake['areas'][0]['last_error'])->toBe('FIRMS returned 503')
        ->and($intake['areas'][0]['backfill'])->toMatchArray(['id' => 'batch-1', 'total' => 200, 'done' => 50, 'failed' => 3, 'percent' => 25.0])
        ->and($intake['areas'][0]['history']['windows_total'])->toBeGreaterThan(0)
        ->and($intake['areas'][0]['history']['windows_done'])->toBeGreaterThanOrEqual(0)
        ->and($intake['areas'][0]['links']['web'])->toBe(route('regions.show', $preset));

    expect($intake['totals'])->toMatchArray([
        'areas' => 1,
        'rows_inserted' => 390,
        'modis_rows' => 100,
        'viirs_rows' => 290,
        'active_backfills' => 1,
        'failed_runs' => 1,
    ]);

    expect($intake['public_feed']['rows_inserted'])->toBe(40)
        ->and($intake['public_feed']['latest'])->toHaveCount(1)
        ->and($intake['public_feed']['latest'][0]['feed'])->toBe('Public feed · VIIRS C2 NOAA-20 · 48h')
        ->and($intake['public_feed']['next_run_at'])->not->toBeNull();

    $sources = collect($intake['recent_runs'])->pluck('source')->all();

    expect($sources)->toContain('area:MODIS_SP', 'sample:gran-chaco__viirs-snpp', 'public:viirs-noaa20:48h')
        ->and(collect($intake['recent_runs'])->pluck('region.slug')->filter()->unique()->all())->toBe(['gran-chaco'])
        ->and(collect($intake['recent_runs'])->firstWhere('source', 'area:MODIS_SP')['duration_s'])->toBe(30)
        ->and(collect($intake['recent_runs'])->firstWhere('source', 'area:MODIS_SP')['label'])->toBe('FIRMS archive · MODIS');
});

it('produces an empty but well-formed payload when nothing has been ingested', function () {
    $intake = app(DataIntake::class)->forPresets();

    expect($intake['areas'])->toBe([])
        ->and($intake['recent_runs'])->toBe([])
        ->and($intake['totals']['rows_inserted'])->toBe(0)
        ->and($intake['totals']['history_percent'])->toBe(0.0)
        ->and($intake['public_feed']['latest'])->toBe([]);
});

it('labels every kind of ingest source for humans', function () {
    $intake = app(DataIntake::class);

    expect($intake->sourceLabel('area:VIIRS_SNPP_SP'))->toBe('FIRMS archive · VIIRS SNPP')
        ->and($intake->sourceLabel('area:VIIRS_NOAA21_NRT'))->toBe('FIRMS near-real-time · VIIRS NOAA-21')
        ->and($intake->sourceLabel('sample:borneo-sumatra__modis'))->toBe('Bundled sample · MODIS')
        ->and($intake->sourceLabel('public:modis:24h'))->toBe('Public feed · MODIS C6.1 (Terra + Aqua) · 24h')
        ->and($intake->sourceLabel('file:whatever.csv'))->toBe('CSV import');
});

it('builds the intake in the same number of queries however many areas there are', function () {
    $queries = function (int $areas): int {
        Region::factory()->preset()->count($areas)->create()->each(fn (Region $region) => IngestRun::factory()->create([
            'region_id' => $region->id,
            'status' => IngestStatus::Failed,
            'error' => 'FIRMS returned 503',
        ]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DataIntake::class)->build();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queries(1))->toBe($queries(5));
});

it('drops a failure once a later run for the area has completed', function () {
    $preset = Region::factory()->preset()->create();

    IngestRun::factory()->create([
        'region_id' => $preset->id,
        'status' => IngestStatus::Failed,
        'error' => 'SQLSTATE[08006] connection timed out',
        'started_at' => now()->subHours(3),
    ]);
    IngestRun::factory()->create([
        'region_id' => $preset->id,
        'started_at' => now()->subHour(),
    ]);

    expect(app(DataIntake::class)->forPresets()['areas'][0]['last_error'])->toBeNull();
});
