<?php

use App\Enums\IngestStatus;
use App\Enums\Instrument;
use App\Models\IngestRun;
use App\Models\Region;
use App\Models\User;
use App\Services\Firms\BackfillPlanner;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('lists runs of public areas and global feeds, newest first, hiding other people\'s areas', function () {
    $preset = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    $private = Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'secret']);
    IngestRun::factory()->create(['region_id' => $preset->id, 'source' => 'area:MODIS_SP', 'instrument' => Instrument::Modis, 'rows_inserted' => 10, 'started_at' => now()->subHour()]);
    IngestRun::factory()->create(['region_id' => null, 'source' => 'public:modis:48h', 'instrument' => Instrument::Modis, 'rows_inserted' => 5, 'started_at' => now()]);
    IngestRun::factory()->create(['region_id' => $private->id, 'source' => 'area:MODIS_SP', 'rows_inserted' => 999]);
    IngestRun::factory()->create(['region_id' => $preset->id, 'source' => 'area:VIIRS_SNPP_SP', 'status' => IngestStatus::Failed, 'error' => 'FIRMS 503', 'started_at' => now()->subDay()]);

    $this->get(route('ingestion'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Ingestion')
            ->where('runs.total', 3)
            ->where('runs.data.0.source', 'public:modis:48h')
            ->where('runs.data.1.label', 'FIRMS archive · MODIS')
            ->where('runs.data.1.region.slug', 'gran-chaco')
            ->where('summary.completed', 2)
            ->where('summary.failed', 1)
            ->where('summary.rows_inserted', 15)
            ->has('areas', 1));
});

it('filters by status and by area, including the public feeds', function () {
    $preset = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    IngestRun::factory()->create(['region_id' => $preset->id, 'source' => 'area:MODIS_SP']);
    IngestRun::factory()->create(['region_id' => $preset->id, 'source' => 'area:MODIS_SP', 'status' => IngestStatus::Failed]);
    IngestRun::factory()->create(['region_id' => null, 'source' => 'public:viirs-snpp:24h']);

    $this->get(route('ingestion', ['status' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page->where('runs.total', 1)->where('filters.status', 'failed'));

    $this->get(route('ingestion', ['area' => 'public-feeds']))
        ->assertInertia(fn (Assert $page) => $page->where('runs.total', 1)->where('runs.data.0.source', 'public:viirs-snpp:24h'));

    $this->get(route('ingestion', ['area' => 'gran-chaco']))
        ->assertInertia(fn (Assert $page) => $page->where('runs.total', 2));
});

it('shows live backfill progress for visible areas', function () {
    $preset = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    DB::table('job_batches')->insert([
        'id' => 'b1', 'name' => BackfillPlanner::batchName($preset), 'total_jobs' => 200, 'pending_jobs' => 150,
        'failed_jobs' => 0, 'failed_job_ids' => '[]', 'options' => null, 'cancelled_at' => null,
        'created_at' => now()->getTimestamp(), 'finished_at' => null,
    ]);

    $this->get(route('ingestion'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches', 1)
            ->where('batches.0.done', 50)
            ->where('batches.0.percent', 25)
            ->where('batches.0.region.slug', 'gran-chaco'));
});
