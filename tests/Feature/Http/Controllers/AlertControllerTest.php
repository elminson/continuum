<?php

use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists alerts of visible regions only, newest period first', function () {
    $preset = Region::factory()->preset()->create();
    $private = Region::factory()->ownedBy(User::factory()->create())->create();
    Alert::factory()->for($preset)->create(['period_start' => '2026-08-03', 'message' => 'older']);
    Alert::factory()->for($preset)->create(['period_start' => '2026-09-07', 'message' => 'newer']);
    Alert::factory()->for($private)->create(['message' => 'secret']);

    $this->get(route('alerts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Alerts')
            ->has('alerts.data', 2)
            ->where('alerts.data.0.message', 'newer')
            ->where('alerts.data.0.region.slug', $preset->slug)
            ->has('regions', 1));
});

it('filters by severity and region', function () {
    $region = Region::factory()->preset()->create();
    $other = Region::factory()->preset()->create();
    Alert::factory()->for($region)->create(['severity' => AlertSeverity::Critical, 'message' => 'critical here']);
    Alert::factory()->for($region)->create(['severity' => AlertSeverity::Watch, 'period_start' => '2026-01-05']);
    Alert::factory()->for($other)->create(['severity' => AlertSeverity::Critical]);

    $this->get(route('alerts.index', ['severity' => 'critical', 'region' => $region->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts.data', 1)
            ->where('alerts.data.0.message', 'critical here')
            ->where('filters.severity', 'critical'));
});

it('rejects an unknown severity', function () {
    $this->from(route('home'))->get(route('alerts.index', ['severity' => 'apocalyptic']))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('severity');
});

it('summarises every visible area for the map, honouring the severity filter but not the region filter', function () {
    $chaco = Region::factory()->preset()->create(['slug' => 'gran-chaco', 'name' => 'Gran Chaco']);
    $quiet = Region::factory()->preset()->create(['slug' => 'quiet-area', 'name' => 'Zzz quiet area']);
    Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'private-area']);
    Alert::factory()->for($chaco)->create(['severity' => AlertSeverity::Critical, 'z_score' => 6.5, 'message' => 'worst']);
    Alert::factory()->for($chaco)->create(['severity' => AlertSeverity::Critical, 'z_score' => 4.2, 'period_start' => '2025-08-04']);
    Alert::factory()->for($chaco)->create(['severity' => AlertSeverity::Watch, 'z_score' => 2.1, 'period_start' => '2024-08-05']);

    $this->get(route('alerts.index', ['region' => 'quiet-area']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('map', 2)
            ->where('map.0.slug', 'gran-chaco')
            ->where('map.0.alerts.total', 3)
            ->where('map.0.alerts.critical', 2)
            ->where('map.0.alerts.watch', 1)
            ->where('map.0.top.message', 'worst')
            ->where('map.0.top.severity', 'critical')
            ->has('map.0.bbox', 4)
            ->where('map.1.slug', 'quiet-area')
            ->where('map.1.alerts.total', 0)
            ->where('map.1.top', null));

    $this->get(route('alerts.index', ['severity' => 'watch']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('map.0.alerts.total', 1)
            ->where('map.0.top.severity', 'watch'));
});
