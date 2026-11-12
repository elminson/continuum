<?php

use App\Enums\Instrument;
use App\Http\Controllers\Api\V1\HotspotExportController;
use App\Models\Detection;
use App\Models\Region;
use App\Models\User;

it('streams every hotspot inside the area for the requested dates', function () {
    Region::factory()->preset()->withBox([-62, -25, -58, -20])->create(['slug' => 'gran-chaco', 'name' => 'Gran Chaco']);
    Detection::factory()->at(-22.5, -60.1, '2026-08-02')->create(['instrument' => Instrument::Modis]);
    Detection::factory()->at(-22.6, -60.2, '2026-08-03')->create(['instrument' => Instrument::Viirs]);
    Detection::factory()->at(-22.6, -60.2, '2026-08-09')->create();
    Detection::factory()->at(10.0, 10.0, '2026-08-02')->create();

    $response = $this->get('/api/v1/regions/gran-chaco/hotspots.csv?from=2026-08-01&to=2026-08-05');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('continuum_gran-chaco_hotspots_2026-08-01_2026-08-05.csv');

    $rows = array_values(array_filter(
        explode("\n", trim($response->streamedContent())),
        fn (string $line) => ! str_starts_with($line, '#'),
    ));

    expect($rows[0])->toBe(implode(',', HotspotExportController::COLUMNS))
        ->and($rows)->toHaveCount(3)
        ->and($rows[1])->toStartWith('2026-08-02,')->toContain(',modis,')
        ->and($rows[2])->toStartWith('2026-08-03,')->toContain(',viirs,');
});

it('filters by instrument', function () {
    Region::factory()->preset()->withBox([-62, -25, -58, -20])->create(['slug' => 'gran-chaco']);
    Detection::factory()->at(-22.5, -60.1, '2026-08-02')->create(['instrument' => Instrument::Modis]);
    Detection::factory()->at(-22.6, -60.2, '2026-08-02')->create(['instrument' => Instrument::Viirs]);

    $content = $this->get('/api/v1/regions/gran-chaco/hotspots.csv?from=2026-08-02&to=2026-08-02&instrument=viirs')->streamedContent();

    expect(substr_count($content, ',viirs,'))->toBe(1)
        ->and($content)->not->toContain(',modis,');
});

it('rejects a range that ends before it starts', function () {
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->getJson('/api/v1/regions/gran-chaco/hotspots.csv?from=2026-08-05&to=2026-08-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('forbids exporting hotspots of a private region to guests', function () {
    Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'private-farm']);

    $this->get('/api/v1/regions/private-farm/hotspots.csv')->assertForbidden();
});
