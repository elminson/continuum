<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Models\User;

it('lists preset regions only', function () {
    Region::factory()->preset()->create(['slug' => 'gran-chaco', 'name' => 'Gran Chaco']);
    Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'private-farm']);

    $this->getJson('/api/v1/regions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'gran-chaco')
        ->assertJsonPath('data.0.is_preset', true)
        ->assertJsonPath('data.0.links.calendar', route('api.v1.regions.calendar', 'gran-chaco'))
        ->assertJsonMissing(['slug' => 'private-farm']);
});

it('shows a preset region with its summary', function () {
    $region = Region::factory()->preset()->withBox([-70, -35, -55, -15])->create(['slug' => 'gran-chaco']);
    CalendarEntry::factory()->forRegion($region)->daily('2026-09-24', Instrument::Modis, 12)->create();

    $this->getJson('/api/v1/regions/gran-chaco')
        ->assertOk()
        ->assertJsonPath('data.bbox', [-70, -35, -55, -15])
        ->assertJsonPath('data.summary.last_date', '2026-09-24')
        ->assertJsonPath('data.summary.last_7_days.modis.cell_days', 12)
        ->assertJsonPath('data.summary.calibration.calibrated', false);
});

it('returns 403 for a private region and 404 for an unknown slug', function () {
    Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'private-farm']);

    $this->getJson('/api/v1/regions/private-farm')->assertForbidden();
    $this->getJson('/api/v1/regions/nowhere')->assertNotFound();
});
