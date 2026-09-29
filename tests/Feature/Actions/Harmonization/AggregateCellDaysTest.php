<?php

use App\Actions\Harmonization\AggregateCellDays;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Models\CellDay;
use App\Models\Detection;
use App\Models\IngestRun;
use App\Support\Geo\BoundingBox;
use App\Support\Geo\Grid;

it('collapses the pixels of one satellite in one cell on one day into a single row', function () {
    $center = Grid::fromConfig()->cellCenter(Grid::fromConfig()->cellIdFor(10.0, 20.0));
    $run = IngestRun::factory()->create();

    Detection::factory()->for($run)->satellite(Satellite::SuomiNpp)->at($center['lat'], $center['lon'], '2026-09-20')->frp(10)->create(['daynight' => 'D', 'confidence' => 'nominal', 'footprint_km2' => 0.15]);
    Detection::factory()->for($run)->satellite(Satellite::SuomiNpp)->at($center['lat'] + 0.001, $center['lon'], '2026-09-20')->frp(25)->create(['daynight' => 'N', 'confidence' => 'high', 'footprint_km2' => 0.15]);
    Detection::factory()->for($run)->satellite(Satellite::SuomiNpp)->at($center['lat'], $center['lon'] + 0.001, '2026-09-20')->frp(5)->create(['daynight' => 'D', 'confidence' => 'low', 'footprint_km2' => 0.2]);
    Detection::factory()->for($run)->satellite(Satellite::Noaa20)->at($center['lat'], $center['lon'], '2026-09-20')->frp(7)->create();
    Detection::factory()->for($run)->satellite(Satellite::SuomiNpp)->at($center['lat'], $center['lon'], '2026-09-21')->frp(3)->create();

    $written = app(AggregateCellDays::class)();

    expect($written)->toBe(3)
        ->and(CellDay::query()->count())->toBe(3);

    $snpp = CellDay::query()->where('satellite', 'N')->where('acq_date', '2026-09-20')->sole();

    expect($snpp->instrument)->toBe(Instrument::Viirs)
        ->and($snpp->detections)->toBe(3)
        ->and($snpp->frp_sum)->toBe(40.0)
        ->and($snpp->frp_max)->toBe(25.0)
        ->and($snpp->footprint_km2)->toBe(0.5)
        ->and($snpp->day_detections)->toBe(2)
        ->and($snpp->night_detections)->toBe(1)
        ->and($snpp->low_confidence)->toBe(1)
        ->and($snpp->nominal_confidence)->toBe(1)
        ->and($snpp->high_confidence)->toBe(1)
        ->and(abs($snpp->latitude - $center['lat']))->toBeLessThan(0.002);

    expect(CellDay::query()->where('satellite', 'N20')->sole()->detections)->toBe(1);
});

it('refreshes existing cell-day rows instead of duplicating them', function () {
    $run = IngestRun::factory()->create();
    Detection::factory()->for($run)->satellite(Satellite::Terra)->at(-25.0, -60.0, '2026-09-20')->frp(12)->create();
    app(AggregateCellDays::class)();

    Detection::factory()->for($run)->satellite(Satellite::Terra)->at(-25.0, -60.0, '2026-09-20')->frp(8)->create();
    app(AggregateCellDays::class)();

    $row = CellDay::query()->sole();

    expect($row->detections)->toBe(2)
        ->and($row->frp_sum)->toBe(20.0);
});

it('limits the aggregation to a date range and box when asked', function () {
    $run = IngestRun::factory()->create();
    Detection::factory()->for($run)->satellite(Satellite::Terra)->at(-25.0, -60.0, '2026-09-20')->create();
    Detection::factory()->for($run)->satellite(Satellite::Terra)->at(-25.0, -60.0, '2026-09-22')->create();
    Detection::factory()->for($run)->satellite(Satellite::Terra)->at(40.0, 10.0, '2026-09-20')->create();

    $dates = [];
    app(AggregateCellDays::class)('2026-09-20', '2026-09-21', new BoundingBox(-70, -35, -55, -15), function (string $date) use (&$dates): void {
        $dates[] = $date;
    });

    expect($dates)->toBe(['2026-09-20'])
        ->and(CellDay::query()->count())->toBe(1)
        ->and(CellDay::query()->sole()->latitude)->toBe(-25.0);
});
