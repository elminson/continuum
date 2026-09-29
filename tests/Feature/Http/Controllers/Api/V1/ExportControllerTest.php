<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Models\User;

it('streams the harmonized calendar as CSV with the receipt as comment lines', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco', 'name' => 'Gran Chaco']);
    CalendarEntry::factory()->forRegion($region)->monthly('2026-08-01', Instrument::Modis, 40)->create();

    $response = $this->get('/api/v1/regions/gran-chaco/export?period=month');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('continuum_gran-chaco_month_ratio_modis.csv');

    $lines = explode("\n", trim($response->streamedContent()));

    expect($lines[0])->toStartWith('# ')->toContain('harmonized burning activity calendar')
        ->and($lines[1])->toContain('Gran Chaco')
        ->and($lines[5])->toStartWith('period_start,harmonized_cell_days,ci_low,ci_high,estimated,source')
        ->and($lines[6])->toStartWith('2026-08-01,40,40,40,0,modis,')
        ->and($lines)->toHaveCount(7);
});

it('forbids exporting a private region to guests', function () {
    Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'private-farm']);

    $this->get('/api/v1/regions/private-farm/export')->assertForbidden();
});
