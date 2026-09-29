<?php

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
