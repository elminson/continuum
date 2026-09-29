<?php

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\Instrument;
use App\Enums\ReferenceSensor;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Support\Stats\FrpHistogram;

it('transfers MODIS-only days onto the VIIRS scale with the ratio and its interval', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->daily('2005-08-10', Instrument::Modis, 50)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2005-09-10', Instrument::Modis, 20)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2020-08-10', Instrument::Modis, 30)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2020-08-10', Instrument::Viirs, 130)->create();
    CalibrationFactor::factory()->for($region)->create(['reference' => ReferenceSensor::Viirs, 'month' => 0, 'value' => 4.0, 'ci_low' => 3.5, 'ci_high' => 4.5]);
    CalibrationFactor::factory()->for($region)->create(['reference' => ReferenceSensor::Viirs, 'month' => 8, 'value' => 3.0, 'ci_low' => 2.8, 'ci_high' => 3.2]);

    $series = app(HarmonizedCalendar::class)->series($region, CalendarPeriod::Day, HarmonizationMethod::Ratio, ReferenceSensor::Viirs);

    expect($series['calibrated'])->toBeTrue()
        ->and($series['reference'])->toBe('viirs')
        ->and($series['points'])->toHaveCount(3);

    [$august2005, $september2005, $august2020] = $series['points'];

    expect($august2005['harmonized'])->toBe(150.0)
        ->and($august2005['low'])->toBe(140.0)
        ->and($august2005['high'])->toBe(160.0)
        ->and($august2005['estimated'])->toBeTrue()
        ->and($august2005['source'])->toBe('modis')
        ->and($august2005['factor'])->toBe(3.0)
        ->and($august2005['viirs'])->toBeNull()
        ->and($august2005['naive'])->toBe(50);

    expect($september2005['harmonized'])->toBe(80.0)
        ->and($september2005['factor'])->toBe(4.0);

    expect($august2020['harmonized'])->toBe(130.0)
        ->and($august2020['estimated'])->toBeFalse()
        ->and($august2020['source'])->toBe('viirs')
        ->and($august2020['modis']['cell_days'])->toBe(30)
        ->and($august2020['naive'])->toBe(130);
});

it('leaves the value empty when the other sensor has no calibration', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->daily('2005-08-10', Instrument::Modis, 50)->create();

    $series = app(HarmonizedCalendar::class)->series($region, CalendarPeriod::Day, HarmonizationMethod::Ratio, ReferenceSensor::Viirs);

    expect($series['calibrated'])->toBeFalse()
        ->and($series['points'][0]['harmonized'])->toBeNull()
        ->and($series['points'][0]['estimated'])->toBeFalse();
});

it('applies the FRP visibility threshold to VIIRS histograms on the MODIS scale', function () {
    $region = Region::factory()->preset()->create();
    $histogram = FrpHistogram::empty();
    $histogram[FrpHistogram::binIndex(5)] = 40;
    $histogram[FrpHistogram::binIndex(30)] = 7;
    $histogram[FrpHistogram::binIndex(500)] = 2;
    CalendarEntry::factory()->forRegion($region)->daily('2030-01-05', Instrument::Viirs, 49)->create(['frp_histogram' => $histogram]);
    CalibrationFactor::factory()->for($region)->create(['method' => HarmonizationMethod::FrpThreshold, 'reference' => ReferenceSensor::Modis, 'month' => 0, 'value' => 20.0, 'ci_low' => 20.0, 'ci_high' => 20.0]);

    $series = app(HarmonizedCalendar::class)->series($region, CalendarPeriod::Day, HarmonizationMethod::FrpThreshold, ReferenceSensor::Modis);

    expect($series['method'])->toBe('frp_threshold')
        ->and($series['points'][0]['harmonized'])->toBe(9.0)
        ->and($series['points'][0]['estimated'])->toBeTrue()
        ->and($series['points'][0]['source'])->toBe('viirs');
});

it('falls back to the ratio method when the threshold method is asked on the VIIRS scale', function () {
    $region = Region::factory()->preset()->create();

    $series = app(HarmonizedCalendar::class)->series($region, CalendarPeriod::Day, HarmonizationMethod::FrpThreshold, ReferenceSensor::Viirs);

    expect($series['method'])->toBe('ratio');
});

it('restricts the series to the requested window', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->daily('2020-08-01', Instrument::Modis, 1)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2020-08-02', Instrument::Modis, 2)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2020-08-03', Instrument::Modis, 3)->create();

    $series = app(HarmonizedCalendar::class)->series($region, CalendarPeriod::Day, HarmonizationMethod::Ratio, ReferenceSensor::Modis, '2020-08-02', '2020-08-02');

    expect(array_column($series['points'], 'period_start'))->toBe(['2020-08-02'])
        ->and($series['points'][0]['harmonized'])->toBe(2.0);
});
