<?php

use App\Actions\Harmonization\BuildCalendar;
use App\Enums\CalendarPeriod;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Models\CalendarEntry;
use App\Models\CellDay;
use App\Models\IngestRun;
use App\Models\Region;
use App\Support\Stats\FrpHistogram;

it('counts distinct cell-days per instrument across satellites and keeps true zeros', function () {
    $region = Region::factory()->preset()->withBox([0, 0, 10, 10])->create();

    // MODIS was fetched for this box on 22 Sep and saw no fire there: a true zero.
    IngestRun::factory()->create([
        'source' => 'area:MODIS_SP',
        'instrument' => Instrument::Modis,
        'meta' => ['from' => '2026-09-22', 'days' => 1, 'bbox' => [-1, -1, 11, 11]],
    ]);

    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(5.0, 5.0, '2026-09-22')->detections(3)->frp(12, 8)->create();
    CellDay::factory()->satellite(Satellite::Noaa20)->at(5.0, 5.0, '2026-09-22')->detections(2)->frp(30, 30)->create();
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(5.0, 5.0, '2026-09-23')->detections(1)->frp(4, 4)->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(40.0, 40.0, '2026-09-22')->detections(1)->frp(50, 50)->create();

    $written = app(BuildCalendar::class)($region, CalendarPeriod::Day);

    expect($written)->toBe(3);

    $viirsDay = CalendarEntry::query()->where('region_id', $region->id)->where('instrument', 'viirs')->where('period_start', '2026-09-22')->sole();

    expect($viirsDay->cell_days)->toBe(1)
        ->and($viirsDay->active_cells)->toBe(1)
        ->and($viirsDay->detections)->toBe(5)
        ->and($viirsDay->frp_sum)->toBe(30.0)
        ->and($viirsDay->frp_max)->toBe(30.0)
        ->and($viirsDay->active_days)->toBe(1)
        ->and($viirsDay->coverage_days)->toBe(1)
        ->and(FrpHistogram::total($viirsDay->frp_histogram))->toBe(1)
        ->and($viirsDay->frp_histogram[FrpHistogram::binIndex(30)])->toBe(1);

    $modisDay = CalendarEntry::query()->where('region_id', $region->id)->where('instrument', 'modis')->where('period_start', '2026-09-22')->sole();

    expect($modisDay->cell_days)->toBe(0)
        ->and($modisDay->coverage_days)->toBe(1)
        ->and(FrpHistogram::total($modisDay->frp_histogram))->toBe(0);

    expect(CalendarEntry::query()->where('instrument', 'modis')->where('period_start', '2026-09-23')->exists())->toBeFalse();
});

it('rolls cell-days up into weeks and months with distinct active cells', function () {
    $region = Region::factory()->preset()->withBox([0, 0, 10, 10])->create();

    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(5.0, 5.0, '2026-09-22')->detections(3)->frp(12)->create();
    CellDay::factory()->satellite(Satellite::SuomiNpp)->at(5.0, 5.0, '2026-09-23')->detections(1)->frp(4)->create();
    CellDay::factory()->satellite(Satellite::Noaa21)->at(6.0, 6.0, '2026-09-24')->detections(2)->frp(200)->create();

    app(BuildCalendar::class)($region, CalendarPeriod::Week);
    app(BuildCalendar::class)($region, CalendarPeriod::Month);

    $week = CalendarEntry::query()->where('region_id', $region->id)->where('period', 'week')->sole();
    $month = CalendarEntry::query()->where('region_id', $region->id)->where('period', 'month')->sole();

    expect($week->period_start->toDateString())->toBe('2026-09-21')
        ->and($week->instrument)->toBe(Instrument::Viirs)
        ->and($week->cell_days)->toBe(3)
        ->and($week->active_cells)->toBe(2)
        ->and($week->active_days)->toBe(3)
        ->and($week->coverage_days)->toBe(3)
        ->and($week->detections)->toBe(6)
        ->and($week->frp_sum)->toBe(216.0)
        ->and(FrpHistogram::total($week->frp_histogram))->toBe(3);

    expect($month->period_start->toDateString())->toBe('2026-09-01')
        ->and($month->cell_days)->toBe(3)
        ->and($month->active_cells)->toBe(2);
});

it('replaces the previous calendar of the region and period', function () {
    $region = Region::factory()->preset()->withBox([0, 0, 10, 10])->create();
    CalendarEntry::factory()->forRegion($region)->daily('2000-01-01', Instrument::Modis, 99)->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(5.0, 5.0, '2026-09-22')->create();

    app(BuildCalendar::class)($region, CalendarPeriod::Day);

    expect(CalendarEntry::query()->where('region_id', $region->id)->where('period', 'day')->pluck('period_start')->map->toDateString()->all())->toBe(['2026-09-22']);
});

it('does not treat data elsewhere on Earth as coverage of this area', function () {
    $region = Region::factory()->preset()->withBox([0, 0, 10, 10])->create();

    // Years of MODIS history for another place, and one fetched week here.
    CellDay::factory()->satellite(Satellite::Terra)->at(40.0, 40.0, '2019-09-23')->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(40.0, 40.0, '2020-09-21')->create();
    CellDay::factory()->satellite(Satellite::Terra)->at(5.0, 5.0, '2026-09-22')->create();

    app(BuildCalendar::class)($region, CalendarPeriod::Week);

    expect(CalendarEntry::query()->where('region_id', $region->id)->where('period', 'week')->pluck('period_start')->map->toDateString()->all())
        ->toBe(['2026-09-21']);
});

it('counts a fetched but fire-free window as coverage for every day requested', function () {
    $region = Region::factory()->preset()->withBox([0, 0, 10, 10])->create();

    IngestRun::factory()->create([
        'source' => 'area:VIIRS_SNPP_SP',
        'instrument' => Instrument::Viirs,
        'meta' => ['from' => '2024-08-05', 'days' => 5, 'bbox' => [-1, -1, 11, 11]],
    ]);
    // A window fetched for a box that does not contain the region is not coverage.
    IngestRun::factory()->create([
        'source' => 'area:MODIS_SP',
        'instrument' => Instrument::Modis,
        'meta' => ['from' => '2024-08-05', 'days' => 5, 'bbox' => [2, 2, 3, 3]],
    ]);

    app(BuildCalendar::class)($region, CalendarPeriod::Week);

    $week = CalendarEntry::query()->where('region_id', $region->id)->where('period', 'week')->sole();

    expect($week->instrument)->toBe(Instrument::Viirs)
        ->and($week->period_start->toDateString())->toBe('2024-08-05')
        ->and($week->coverage_days)->toBe(5)
        ->and($week->cell_days)->toBe(0);
});
