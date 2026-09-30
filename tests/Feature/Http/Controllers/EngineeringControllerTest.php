<?php

use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\CalibrationFactor;
use App\Models\IngestRun;
use App\Models\Region;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Cache::flush());

it('renders the how-it-works page with live totals and no featured area when nothing is calibrated', function () {
    Region::factory()->preset()->create();
    IngestRun::factory()->create(['source' => 'area:MODIS_SP', 'rows_read' => 120, 'rows_inserted' => 100]);

    $this->get(route('engineering'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Engineering')
            ->where('totals.public_areas', 1)
            ->where('totals.area_windows', 1)
            ->where('totals.rows_read', 120)
            ->where('config.window_days', 5)
            ->where('featured', null));
});

it('features the public area with the longest calibrated overlap', function () {
    $short = Region::factory()->preset()->create(['slug' => 'short']);
    $long = Region::factory()->preset()->create(['slug' => 'long']);

    foreach ([[$short, 20], [$long, 400]] as [$region, $days]) {
        CalibrationFactor::factory()->create([
            'region_id' => $region->id,
            'method' => HarmonizationMethod::Ratio,
            'reference' => ReferenceSensor::Modis,
            'month' => CalibrationFactor::ALL_YEAR,
            'sample_days' => $days,
            'validation' => ['train_days' => 300, 'test_days' => 100, 'harmonized' => ['mae' => 9.3, 'r2' => 0.71], 'naive' => ['mae' => 65.9, 'r2' => -11.5], 'mae_improvement' => 0.858],
        ]);
    }

    $this->get(route('engineering'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('featured.slug', 'long')
            ->where('featured.ratio.sample_days', 400)
            ->where('featured.ratio.validation.mae_improvement', 0.858)
            ->where('featured.ratio.validation.naive_mae', 65.9)
            ->has('featured.pairs')
            ->has('featured.threshold_curve'));
});
