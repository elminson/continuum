<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;

it('returns the harmonized monthly series with its method receipt', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    CalendarEntry::factory()->forRegion($region)->monthly('2026-08-01', Instrument::Modis, 40)->create();
    CalendarEntry::factory()->forRegion($region)->monthly('2026-08-01', Instrument::Viirs, 160)->create();
    CalibrationFactor::factory()->for($region)->create(['value' => 0.25, 'sample_days' => 30]);

    $this->getJson('/api/v1/regions/gran-chaco/calendar?period=month&method=ratio&reference=modis')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.period_start', '2026-08-01')
        ->assertJsonPath('data.0.harmonized', 40)
        ->assertJsonPath('data.0.estimated', false)
        ->assertJsonPath('data.0.viirs.cell_days', 160)
        ->assertJsonPath('meta.method', 'ratio')
        ->assertJsonPath('meta.reference', 'modis')
        ->assertJsonPath('meta.calibrated', true)
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('meta.receipt.region.slug', 'gran-chaco')
        ->assertJsonPath('meta.receipt.method.factor', 0.25)
        ->assertJsonPath('meta.receipt.method.overlap_days', 30)
        ->assertJsonPath('meta.receipt.grid.harmonization_cell_km', 1);
});

it('returns 422 for an unknown period', function () {
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->getJson('/api/v1/regions/gran-chaco/calendar?period=decade')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['period']);
});

it('returns 422 when the window ends before it starts', function () {
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->getJson('/api/v1/regions/gran-chaco/calendar?from=2026-09-10&to=2026-09-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});
