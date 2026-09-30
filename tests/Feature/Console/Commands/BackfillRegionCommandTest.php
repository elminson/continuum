<?php

use App\Jobs\BackfillRegionWindowJob;
use App\Models\Region;
use App\Services\Firms\BackfillPlanner;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

it('refuses to run without a FIRMS map key', function () {
    config()->set('continuum.firms.map_key', null);
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->artisan('firms:backfill', ['region' => 'gran-chaco'])
        ->expectsOutputToContain('FIRMS_MAP_KEY is not set')
        ->assertFailed();
});

it('rejects an unknown region slug', function () {
    config()->set('continuum.firms.map_key', 'test-key');

    $this->artisan('firms:backfill', ['region' => 'nowhere'])
        ->expectsOutputToContain('Unknown region slug')
        ->assertExitCode(2);
});

it('queues one five-day window per source as a batch', function () {
    Bus::fake();
    config()->set('continuum.firms.map_key', 'test-key');
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->artisan('firms:backfill', [
        'region' => 'gran-chaco',
        '--source' => ['MODIS_SP'],
        '--from' => '2024-01-01',
        '--to' => '2024-01-12',
    ])->expectsOutputToContain('3 Area API requests')->assertSuccessful();

    Bus::assertBatched(function ($batch) {
        $jobs = $batch->jobs;

        return $batch->name === 'backfill:gran-chaco'
            && $jobs->count() === 3
            && $jobs->every(fn ($job) => $job instanceof BackfillRegionWindowJob)
            && $jobs[0]->from === '2024-01-01' && $jobs[0]->days === 5
            && $jobs[2]->from === '2024-01-11' && $jobs[2]->days === 2;
    });
});

it('refuses to queue a second batch while one is still running for the region', function () {
    Bus::fake();
    config()->set('continuum.firms.map_key', 'test-key');
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    DB::table('job_batches')->insert([
        'id' => 'running-batch',
        'name' => BackfillPlanner::batchName($region),
        'total_jobs' => 100,
        'pending_jobs' => 60,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => null,
        'cancelled_at' => null,
        'created_at' => now()->getTimestamp(),
        'finished_at' => null,
    ]);

    $this->artisan('firms:backfill', ['region' => 'gran-chaco', '--source' => ['MODIS_SP'], '--from' => '2024-01-01', '--to' => '2024-01-05'])
        ->expectsOutputToContain('already running')
        ->expectsOutputToContain('40 of 100 windows done')
        ->assertFailed();

    Bus::assertNothingBatched();

    $this->artisan('firms:backfill', ['region' => 'gran-chaco', '--source' => ['MODIS_SP'], '--from' => '2024-01-01', '--to' => '2024-01-05', '--force' => true])
        ->assertSuccessful();

    Bus::assertBatchCount(1);
});
