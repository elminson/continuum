<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the harmonization lab with the overlap pairs and threshold curve', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->daily('2024-06-01', Instrument::Modis, 10)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2024-06-01', Instrument::Viirs, 40)->create();

    $this->get(route('regions.lab', $region))
        ->assertInertia(fn (Assert $page) => $page
            ->component('regions/Lab')
            ->where('region.slug', $region->slug)
            ->has('pairs', 1)
            ->where('pairs.0', ['date' => '2024-06-01', 'month' => 6, 'modis' => 10, 'viirs' => 40])
            ->has('thresholdCurve', 26)
            ->where('thresholdCurve.0.predicted', 40)
            ->where('thresholdCurve.0.observed', 10)
            ->missing('detectionCurve')
            ->missing('retirement'));
});

it('forbids the lab of a private region to guests', function () {
    $region = Region::factory()->ownedBy(User::factory()->create())->create();

    $this->get(route('regions.lab', $region))->assertForbidden();
});
