<?php

use App\Enums\CalendarPeriod;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Models\Alert;
use App\Models\CalendarEntry;
use App\Models\CellDay;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a flagged period with where, when and how it compares, the heavy part deferred', function () {
    $region = Region::factory()->preset()->withBox([0, 0, 10, 10])->create(['name' => 'Gran Chaco']);
    $alert = Alert::factory()->for($region)->create(['period' => CalendarPeriod::Week, 'period_start' => '2026-09-21']);
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(5.0, 5.0, '2026-09-22')->detections(3)->frp(40, 30)->create();
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(5.0, 5.0, '2026-09-23')->detections(1)->frp(10, 10)->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(6.0, 6.0, '2026-09-23')->frp(1, 1)->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(6.0, 6.0, '2026-09-29')->create(); // next week
    CellDay::factory()->satellite(Satellite::Terra)->at(40.0, 40.0, '2026-09-23')->create(); // outside the area
    CalendarEntry::factory()->forRegion($region)->weekly('2025-09-22', Instrument::Modis, 30)->create();
    CalendarEntry::factory()->forRegion($region)->weekly('2026-09-21', Instrument::Modis, 300)->create();

    $this->get(route('alerts.show', $alert))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('alerts/Show')
            ->where('alert.id', $alert->id)
            ->where('region.name', 'Gran Chaco')
            ->missing('detail')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('detail.from', '2026-09-21')
                ->where('detail.to', '2026-09-27')
                ->where('detail.footprint.cells', 2)
                ->where('detail.footprint.features.0.properties.days', 2)
                ->where('detail.footprint.features.0.properties.sensors', 'V')
                ->has('detail.daily', 7)
                ->where('detail.daily.2.date', '2026-09-23')
                ->where('detail.daily.2.modis', 1)
                ->where('detail.daily.2.viirs', 1)
                ->has('detail.history', 2)
                ->where('detail.history.1.current', true)
                ->where('detail.history.1.value', 300)));
});

it('hides alerts of other people\'s private areas', function () {
    $alert = Alert::factory()->for(Region::factory()->ownedBy(User::factory()->create()))->create();

    $this->get(route('alerts.show', $alert))->assertForbidden();
});
