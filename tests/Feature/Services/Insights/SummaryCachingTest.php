<?php

use App\Enums\IngestStatus;
use App\Models\IngestRun;
use App\Models\Region;
use App\Services\Firms\BackfillPlanner;
use App\Services\Insights\RegionSummary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Cache::flush());

it('counts history progress exactly like the job planner without building jobs', function () {
    $region = Region::factory()->preset()->create(['west' => -60, 'south' => -20, 'east' => -59, 'north' => -19]);
    $planner = app(BackfillPlanner::class);
    $box = $region->bbox()->padded((float) config('continuum.firms.fetch_padding_deg', 0));

    foreach (array_slice($planner->fullHistory($region), 0, 7) as $job) {
        IngestRun::factory()->create([
            'region_id' => $region->id,
            'source' => "area:{$job->source->value}",
            'instrument' => $job->source->instrument(),
            'status' => IngestStatus::Completed,
            'meta' => ['from' => $job->from, 'days' => $job->days, 'bbox' => $box->toArray()],
        ]);
    }

    $remaining = count($planner->fullHistory($region));
    $progress = $planner->historyProgress($region);

    expect($progress['done'])->toBe(7)
        ->and($progress['total'] - $progress['done'])->toBe($remaining);
});

it('computes global totals per sensor from cell-days', function () {
    DB::table('cell_days')->insert([
        ['cell_id' => 1, 'acq_date' => '2026-09-01', 'satellite' => 'T', 'instrument' => 'modis', 'calendar_cell_id' => 1, 'latitude' => 1, 'longitude' => 1, 'detections' => 2, 'frp_sum' => 1, 'frp_max' => 1, 'footprint_km2' => 1],
        ['cell_id' => 2, 'acq_date' => '2026-09-01', 'satellite' => 'A', 'instrument' => 'modis', 'calendar_cell_id' => 1, 'latitude' => 1, 'longitude' => 1, 'detections' => 3, 'frp_sum' => 1, 'frp_max' => 1, 'footprint_km2' => 1],
        ['cell_id' => 1, 'acq_date' => '2026-09-03', 'satellite' => 'N', 'instrument' => 'viirs', 'calendar_cell_id' => 1, 'latitude' => 1, 'longitude' => 1, 'detections' => 7, 'frp_sum' => 1, 'frp_max' => 1, 'footprint_km2' => 1],
    ]);

    $global = app(RegionSummary::class)->buildGlobal();

    expect($global)->toMatchArray([
        'first_date' => '2026-09-01',
        'last_date' => '2026-09-03',
        'days' => 2,
        'detections' => 12,
        'cell_days' => 3,
        'modis' => ['detections' => 5, 'cell_days' => 2, 'satellites' => 2],
        'viirs' => ['detections' => 7, 'cell_days' => 1, 'satellites' => 1],
    ]);
});

it('serves region summaries from cache until the region is re-harmonized', function () {
    $region = Region::factory()->preset()->create(['harmonized_at' => now()->subHour()]);
    $summary = app(RegionSummary::class);

    $summary->for($region);
    DB::enableQueryLog();
    $summary->for($region);
    expect(DB::getQueryLog())->toBe([]);

    $region->forceFill(['harmonized_at' => now()])->save();
    $summary->for($region->fresh());
    expect(DB::getQueryLog())->not->toBe([]);
});

it('warms every dashboard cache', function () {
    Region::factory()->preset()->create();

    $this->artisan('continuum:warm-cache')->assertSuccessful();

    expect(Cache::has(RegionSummary::GLOBAL_KEY))->toBeTrue()
        ->and(Cache::has('continuum.data-intake'))->toBeTrue();
});
