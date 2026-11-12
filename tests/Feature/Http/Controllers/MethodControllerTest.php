<?php

use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the method page with the grid and calibration settings', function () {
    $this->get(route('method'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Method')
            ->where('gridKm', 1)
            ->where('calendarCellDeg', 0.25)
            ->has('frpEdges', 26)
            ->has('calibration.method_version')
            ->has('anomalies.thresholds.critical'));
});

it('lists held-out validation for visible areas, weakest first, with their weaknesses', function () {
    $strong = Region::factory()->preset()->create(['name' => 'Strong']);
    $weak = Region::factory()->preset()->create(['name' => 'Weak']);
    $private = Region::factory()->ownedBy(User::factory()->create())->create(['name' => 'Private']);

    $validation = fn (float $r2, float $improvement) => [
        'test_days' => 100,
        'harmonized' => ['mae' => 2.0, 'r2' => $r2],
        'naive' => ['mae' => 20.0],
        'mae_improvement' => $improvement,
        'correlation' => 0.9,
    ];

    CalibrationFactor::factory()->for($strong)->create(['sample_days' => 4000, 'validation' => $validation(0.9, 0.8)]);
    CalibrationFactor::factory()->for($weak)->create(['sample_days' => 27, 'validation' => $validation(0.3, 0.1)]);
    CalibrationFactor::factory()->for($private)->create(['validation' => $validation(0.1, 0.1)]);

    $this->get(route('method'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('validation', 2)
            ->where('validation.0.region', 'Weak')
            ->where('validation.0.issues', ['small_sample', 'weak_daily_fit', 'barely_beats_naive'])
            ->where('validation.1.region', 'Strong')
            ->where('validation.1.issues', [])
            ->where('thresholds.small_sample_days', 90));
});
