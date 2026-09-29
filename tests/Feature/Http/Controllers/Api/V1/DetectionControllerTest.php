<?php

use App\Enums\Satellite;
use App\Models\CellDay;
use App\Models\Detection;
use App\Models\IngestRun;

it('returns the cell-days inside the box on the requested date as GeoJSON', function () {
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(-25.0, -60.0, '2026-09-24')->detections(4)->frp(55.5, 30)->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(-26.0, -61.0, '2026-09-24')->detections(1)->frp(20)->create();
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(-25.0, -60.0, '2026-09-23')->create();
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(10.0, 10.0, '2026-09-24')->create();

    $response = $this->getJson('/api/v1/hotspots?bbox=-70,-35,-55,-15&date=2026-09-24')
        ->assertOk()
        ->assertJsonPath('type', 'FeatureCollection')
        ->assertJsonCount(2, 'features')
        ->assertJsonPath('features.0.geometry.type', 'Point')
        ->assertJsonPath('features.0.properties.instrument', 'viirs')
        ->assertJsonPath('features.0.properties.satellite', 'Suomi NPP')
        ->assertJsonPath('features.0.properties.detections', 4)
        ->assertJsonPath('features.0.properties.frp_sum', 55.5)
        ->assertJsonPath('meta.count', 2)
        ->assertJsonPath('meta.raw', false)
        ->assertJsonPath('meta.grid_km', 1);

    $bounds = $response->json('features.0.properties.bounds');

    expect($bounds[0])->toBeLessThan(-60.0)
        ->and($bounds[2])->toBeGreaterThan(-60.0)
        ->and($bounds[1])->toBeLessThan(-25.0)
        ->and($bounds[3])->toBeGreaterThan(-25.0);
});

it('filters by instrument and honours the limit', function () {
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(-25.0, -60.0, '2026-09-24')->frp(10)->create();
    CellDay::factory()->satellite(Satellite::Noaa20)->at(-25.5, -60.5, '2026-09-24')->frp(90)->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(-26.0, -61.0, '2026-09-24')->create();

    $this->getJson('/api/v1/hotspots?bbox=-70,-35,-55,-15&date=2026-09-24&instrument=viirs&limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.satellite', 'NOAA-20')
        ->assertJsonPath('meta.instrument', 'viirs')
        ->assertJsonPath('meta.limit', 1);
});

it('returns raw pixels when asked', function () {
    $run = IngestRun::factory()->create();
    Detection::factory()->for($run)->satellite(Satellite::Aqua)->at(-25.0, -60.0, '2026-09-24')->frp(33)->create(['confidence' => 'high']);

    $this->getJson('/api/v1/hotspots?bbox=-70,-35,-55,-15&date=2026-09-24&raw=1')
        ->assertOk()
        ->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.satellite', 'Aqua')
        ->assertJsonPath('features.0.properties.frp', 33)
        ->assertJsonPath('features.0.properties.confidence', 'high')
        ->assertJsonPath('meta.raw', true);
});

it('returns 422 for a malformed or inverted box and for a missing date', function () {
    $this->getJson('/api/v1/hotspots?bbox=east,south,west,north&date=2026-09-24')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bbox']);

    $this->getJson('/api/v1/hotspots?bbox=-55,-35,-70,-15&date=2026-09-24')
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('bbox');

    $this->getJson('/api/v1/hotspots?bbox=-70,-35,-55,-15')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});
