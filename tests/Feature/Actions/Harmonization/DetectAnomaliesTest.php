<?php

use App\Actions\Harmonization\DetectAnomalies;
use App\Enums\AlertSeverity;
use App\Enums\CalendarPeriod;
use App\Enums\Instrument;
use App\Models\Alert;
use App\Models\CalendarEntry;
use App\Models\Region;

const ISO_WEEK_32_MONDAYS = [
    2019 => '2019-08-05',
    2020 => '2020-08-03',
    2021 => '2021-08-09',
    2022 => '2022-08-08',
    2023 => '2023-08-07',
    2024 => '2024-08-05',
];

it('flags a week that is far above the same week of other years', function () {
    $region = Region::factory()->preset()->create();

    foreach (ISO_WEEK_32_MONDAYS as $year => $monday) {
        CalendarEntry::factory()->forRegion($region)->weekly($monday, Instrument::Modis, $year === 2024 ? 500 : 100)->create();
    }

    $written = app(DetectAnomalies::class)($region, CalendarPeriod::Week);

    expect($written)->toBe(1);

    $alert = Alert::query()->where('region_id', $region->id)->sole();

    expect($alert->period_start->toDateString())->toBe('2024-08-05')
        ->and($alert->period)->toBe(CalendarPeriod::Week)
        ->and($alert->observed)->toBe(500.0)
        ->and($alert->expected)->toBe(100.0)
        ->and($alert->spread)->toBe(25.0)
        ->and($alert->z_score)->toBe(16.0)
        ->and($alert->percentile)->toBe(100.0)
        ->and($alert->severity)->toBe(AlertSeverity::Critical)
        ->and($alert->climatology_years)->toBe(5)
        ->and($alert->message)->toContain('Week of 5 Aug 2024')->toContain('500')->toContain('5x')->toContain('5 other years');
});

it('grades the severity from the z-score thresholds', function () {
    $region = Region::factory()->preset()->create();

    foreach (ISO_WEEK_32_MONDAYS as $year => $monday) {
        CalendarEntry::factory()->forRegion($region)->weekly($monday, Instrument::Modis, $year === 2024 ? 160 : 100)->create();
    }

    app(DetectAnomalies::class)($region, CalendarPeriod::Week);

    $alert = Alert::query()->where('region_id', $region->id)->sole();

    expect($alert->z_score)->toBe(2.4)
        ->and($alert->severity)->toBe(AlertSeverity::Watch);
});

it('does not raise alerts when fewer years than the minimum climatology exist', function () {
    $region = Region::factory()->preset()->create();

    foreach (array_slice(ISO_WEEK_32_MONDAYS, 3, null, true) as $year => $monday) {
        CalendarEntry::factory()->forRegion($region)->weekly($monday, Instrument::Modis, $year === 2024 ? 900 : 100)->create();
    }

    $written = app(DetectAnomalies::class)($region, CalendarPeriod::Week);

    expect($written)->toBe(0)
        ->and(Alert::query()->where('region_id', $region->id)->exists())->toBeFalse();
});

it('replaces previous alerts of the same period and method', function () {
    $region = Region::factory()->preset()->create();
    Alert::factory()->for($region)->create(['period' => CalendarPeriod::Week, 'period_start' => '2010-01-04']);

    foreach (ISO_WEEK_32_MONDAYS as $monday) {
        CalendarEntry::factory()->forRegion($region)->weekly($monday, Instrument::Modis, 100)->create();
    }

    app(DetectAnomalies::class)($region, CalendarPeriod::Week);

    expect(Alert::query()->where('region_id', $region->id)->exists())->toBeFalse();
});
