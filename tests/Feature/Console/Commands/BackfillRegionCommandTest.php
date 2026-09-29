<?php

use App\Jobs\BackfillRegionWindowJob;
use App\Models\Region;
use Illuminate\Support\Facades\Bus;

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
