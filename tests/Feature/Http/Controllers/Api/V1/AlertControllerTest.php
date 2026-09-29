<?php

use App\Enums\CalendarPeriod;
use App\Models\Alert;
use App\Models\Region;

it('lists the alerts of a region and filters by period', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    Alert::factory()->for($region)->create(['period' => CalendarPeriod::Week, 'message' => 'weekly']);
    Alert::factory()->for($region)->create(['period' => CalendarPeriod::Month, 'period_start' => '2026-09-01', 'message' => 'monthly']);

    $this->getJson('/api/v1/regions/gran-chaco/alerts')->assertOk()->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/regions/gran-chaco/alerts?period=month')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.message', 'monthly')
        ->assertJsonPath('data.0.severity', 'warning');
});

it('returns 422 for an unknown period', function () {
    Region::factory()->preset()->create(['slug' => 'gran-chaco']);

    $this->getJson('/api/v1/regions/gran-chaco/alerts?period=hour')->assertUnprocessable();
});
