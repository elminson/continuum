<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;

it('returns the climatological season of a region', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    foreach ([5, 5, 5, 5, 10, 20, 60, 120, 200, 110, 40, 10] as $index => $value) {
        CalendarEntry::factory()->forRegion($region)->monthly(sprintf('2023-%02d-01', $index + 1), Instrument::Modis, $value)->create();
    }

    $this->getJson('/api/v1/regions/gran-chaco/critical-periods')
        ->assertOk()
        ->assertJsonPath('data.peak.label', 'Sep')
        ->assertJsonPath('data.season.start_label', 'Aug')
        ->assertJsonPath('data.season.end_label', 'Oct')
        ->assertJsonCount(12, 'data.climatology');
});
