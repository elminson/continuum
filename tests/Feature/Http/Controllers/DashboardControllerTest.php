<?php

use App\Enums\Instrument;
use App\Models\Alert;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the dashboard for guests with preset regions and their summaries', function () {
    $preset = Region::factory()->preset()->create(['name' => 'Gran Chaco']);
    Region::factory()->ownedBy(User::factory()->create())->create(['name' => 'Private area']);
    CalendarEntry::factory()->forRegion($preset)->daily('2026-09-20', Instrument::Viirs, 120)->create();
    Alert::factory()->for($preset)->create(['message' => 'Burning is unusual.']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('regions', 1)
            ->where('regions.0.name', 'Gran Chaco')
            ->where('regions.0.summary.last_date', '2026-09-20')
            ->where('regions.0.summary.last_7_days.viirs.cell_days', 120)
            ->has('alerts', 1)
            ->where('alerts.0.message', 'Burning is unusual.')
            ->has('global.regions'));
});

it('includes the visitor\'s own regions when signed in', function () {
    $user = User::factory()->create();
    Region::factory()->preset()->create();
    Region::factory()->ownedBy($user)->create(['name' => 'My farm']);
    Region::factory()->ownedBy(User::factory()->create())->create(['name' => 'Someone else']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('regions', 2)
            ->where('regions.1.name', 'My farm')
            ->where('regions.1.is_owner', true));
});
