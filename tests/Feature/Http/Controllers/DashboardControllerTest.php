<?php

use App\Enums\Instrument;
use App\Models\Alert;
use App\Models\CalendarEntry;
use App\Models\IngestRun;
use App\Models\Region;
use App\Models\User;
use Inertia\Inertia;
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

it('serves the data-intake panel as a deferred prop covering public areas only', function () {
    $preset = Region::factory()->preset()->create(['name' => 'Gran Chaco', 'slug' => 'gran-chaco']);
    Region::factory()->ownedBy(User::factory()->create())->create(['slug' => 'private-area']);
    IngestRun::factory()->create(['region_id' => $preset->id, 'source' => 'area:MODIS_SP', 'instrument' => Instrument::Modis, 'rows_inserted' => 42]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->missing('intake'));

    $this->get(route('home'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'intake',
    ])
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Dashboard')
        ->assertJsonCount(1, 'props.intake.areas')
        ->assertJsonPath('props.intake.areas.0.slug', 'gran-chaco')
        ->assertJsonPath('props.intake.areas.0.rows_inserted', 42)
        ->assertJsonPath('props.intake.totals.rows_inserted', 42)
        ->assertJsonCount(1, 'props.intake.recent_runs')
        ->assertJsonStructure(['props' => ['intake' => ['public_feed', 'generated_at']]]);
});
