<?php

use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Detection;
use App\Models\Region;

it('loads the bundled Gran Chaco sample and calibrates the region', function () {
    $this->artisan('continuum:demo', ['--region' => ['gran-chaco'], '--fresh' => true])
        ->assertSuccessful();

    $region = Region::query()->where('slug', 'gran-chaco')->sole();

    expect($region->is_preset)->toBeTrue()
        ->and(Detection::query()->count())->toBeGreaterThan(30000)
        ->and(Detection::query()->where('instrument', 'modis')->count())->toBe(3645)
        ->and(CalendarEntry::query()->where('region_id', $region->id)->where('period', 'day')->count())->toBe(16)
        ->and(CalibrationFactor::query()->where('region_id', $region->id)->where('method', 'ratio')->where('reference', 'modis')->where('month', 0)->sole()->value)->toBeBetween(0.1, 0.5)
        ->and(CalibrationFactor::query()->where('region_id', $region->id)->where('method', 'frp_threshold')->exists())->toBeTrue()
        ->and($region->fresh()->harmonized_at)->not->toBeNull();
});

it('warns when a preset has no bundled sample', function () {
    config()->set('continuum.presets', [[
        'slug' => 'nowhere',
        'name' => 'Nowhere',
        'bbox' => [0, 0, 1, 1],
    ]]);

    $this->artisan('continuum:demo', ['--region' => ['nowhere']])
        ->expectsOutputToContain('No bundled sample for nowhere')
        ->assertSuccessful();

    expect(Detection::query()->count())->toBe(0);
});
