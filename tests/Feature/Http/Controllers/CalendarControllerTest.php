<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the calendar of a preset region for guests with the default filters', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->monthly('2026-08-01', Instrument::Modis, 40)->create();
    CalibrationFactor::factory()->for($region)->create();

    $this->get(route('regions.show', $region))
        ->assertInertia(fn (Assert $page) => $page
            ->component('regions/Show')
            ->where('region.slug', $region->slug)
            ->where('filters.period', 'month')
            ->where('filters.method', 'ratio')
            ->where('filters.reference', 'modis')
            ->has('options.methods', 2)
            ->has('series.points', 1)
            ->where('series.points.0.harmonized', 40)
            ->where('series.calibrated', true)
            ->has('factors', 1)
            ->has('critical.climatology', 12)
            ->has('alerts', 0));
});

it('applies the period, method and reference from the query string', function () {
    $region = Region::factory()->preset()->create();

    $this->get(route('regions.show', [$region, 'period' => 'week', 'method' => 'frp_threshold', 'reference' => 'modis']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('regions/Show')
            ->where('filters.period', 'week')
            ->where('filters.method', 'frp_threshold')
            ->where('series.method', 'frp_threshold'));
});

it('rejects an unknown period', function () {
    $region = Region::factory()->preset()->create();

    $this->from(route('regions.index'))
        ->get(route('regions.show', [$region, 'period' => 'decade']))
        ->assertRedirect(route('regions.index'))
        ->assertSessionHasErrors('period');
});

it('hides a private region from guests and other users but shows it to its owner', function () {
    $owner = User::factory()->create();
    $region = Region::factory()->ownedBy($owner)->create();

    $this->get(route('regions.show', $region))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('regions.show', $region))->assertForbidden();
    $this->actingAs($owner)->get(route('regions.show', $region))->assertInertia(fn (Assert $page) => $page->component('regions/Show'));
});

it('returns 404 for an unknown slug', function () {
    $this->get('/regions/does-not-exist')->assertNotFound();
});
