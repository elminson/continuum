<?php

use App\Enums\HarmonizationMethod;
use App\Models\CalibrationFactor;
use App\Models\Region;

it('lists the calibration factors of a region in method order', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    CalibrationFactor::factory()->for($region)->create(['method' => HarmonizationMethod::Ratio, 'month' => 8, 'value' => 0.3]);
    CalibrationFactor::factory()->for($region)->create(['method' => HarmonizationMethod::FrpThreshold, 'month' => 0, 'value' => 20]);
    CalibrationFactor::factory()->for($region)->create(['method' => HarmonizationMethod::Ratio, 'month' => 0, 'value' => 0.25]);

    $this->getJson('/api/v1/regions/gran-chaco/calibration')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.method', 'frp_threshold')
        ->assertJsonPath('data.0.unit', 'MW')
        ->assertJsonPath('data.0.month', null)
        ->assertJsonPath('data.1.month', null)
        ->assertJsonPath('data.1.value', 0.25)
        ->assertJsonPath('data.2.month', 8)
        ->assertJsonPath('data.2.unit', 'ratio');
});
