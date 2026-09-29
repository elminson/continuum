<?php

use App\Jobs\HarmonizeRegionJob;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

it('queues the full history when an area is created and a FIRMS key is configured', function () {
    Bus::fake();
    config()->set('continuum.firms.map_key', 'test-key');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('regions.store'), [
        'name' => 'Pantanal',
        'west' => -60,
        'south' => -22,
        'east' => -54,
        'north' => -15,
    ]);

    $region = Region::query()->where('name', 'Pantanal')->firstOrFail();
    $response->assertRedirect(route('regions.show', $region));
    Bus::assertBatched(fn ($batch) => $batch->name === "backfill:{$region->slug}" && $batch->jobs->count() > 100);
    Bus::assertNotDispatched(HarmonizeRegionJob::class);
});

it('only harmonizes existing detections when no FIRMS key is configured', function () {
    Bus::fake();
    config()->set('continuum.firms.map_key', null);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('regions.store'), [
        'name' => 'Pantanal',
        'west' => -60,
        'south' => -22,
        'east' => -54,
        'north' => -15,
    ]);

    Bus::assertDispatched(HarmonizeRegionJob::class);
    Bus::assertNothingBatched();
});

it('lets an owner trigger a backfill for their area but not for someone else\'s', function () {
    Bus::fake();
    config()->set('continuum.firms.map_key', 'test-key');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $region = Region::factory()->ownedBy($owner)->create();

    $this->actingAs($other)->post(route('regions.backfill', $region))->assertForbidden();
    $this->actingAs($owner)->post(route('regions.backfill', $region))->assertRedirect();

    Bus::assertBatched(fn ($batch) => $batch->name === "backfill:{$region->slug}");
});

it('does not queue a second backfill while one is running', function () {
    config()->set('queue.default', 'database');
    config()->set('continuum.firms.map_key', 'test-key');
    config()->set('continuum.firms.nrt_tail_days', 1);
    $owner = User::factory()->create();
    $region = Region::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)->post(route('regions.backfill', $region));
    $first = DB::table('job_batches')->count();
    $this->actingAs($owner)->post(route('regions.backfill', $region))->assertSessionHas('success');

    expect(session('success'))->toContain('already running')
        ->and(DB::table('job_batches')->count())->toBe($first);
});
