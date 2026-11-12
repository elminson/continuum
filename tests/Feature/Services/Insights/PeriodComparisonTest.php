<?php

use App\Enums\HarmonizationMethod;
use App\Enums\Instrument;
use App\Enums\ReferenceSensor;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Services\Insights\PeriodComparison;

it('compares a window with the same dates in every earlier year of the record', function () {
    $region = Region::factory()->preset()->create();

    // Three earlier years with 10, 20 and 30 cell-days in early September, then 60 now.
    foreach ([2022 => 10, 2023 => 20, 2024 => 30, 2025 => 60] as $year => $cellDays) {
        CalendarEntry::factory()->forRegion($region)->daily("{$year}-09-01", Instrument::Modis, $cellDays)->create();
        CalendarEntry::factory()->forRegion($region)->daily("{$year}-09-20", Instrument::Modis, 1)->create();
    }

    $result = app(PeriodComparison::class)->build($region, '2025-09-01', '2025-09-10', HarmonizationMethod::Ratio, ReferenceSensor::Modis);

    expect($result)->toMatchArray([
        'from' => '2025-09-01',
        'to' => '2025-09-10',
        'days' => 10,
        'current' => 60.0,
        'hotspots' => 60,
        'median' => 20.0,
        'change' => 2.0,
        'percentile' => 100.0,
        'years' => 3,
        'reason' => null,
    ])->and($result['history'])->toBe([
        ['year' => 2022, 'value' => 10.0],
        ['year' => 2023, 'value' => 20.0],
        ['year' => 2024, 'value' => 30.0],
    ]);
});

it('counts days without detections as zero and ignores years before the record', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->daily('2024-01-15', Instrument::Modis, 5)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2025-03-01', Instrument::Modis, 8)->create();

    $result = app(PeriodComparison::class)->build($region, '2025-03-01', '2025-03-31', HarmonizationMethod::Ratio, ReferenceSensor::Modis);

    expect($result['current'])->toBe(8.0)
        ->and($result['years'])->toBe(1)
        ->and($result['median'])->toBe(0.0)
        ->and($result['change'])->toBeNull();
});

it('refuses to compare windows longer than a year', function () {
    $region = Region::factory()->preset()->create();
    CalendarEntry::factory()->forRegion($region)->daily('2020-01-01', Instrument::Modis, 5)->create();

    $result = app(PeriodComparison::class)->build($region, '2023-01-01', '2024-12-31', HarmonizationMethod::Ratio, ReferenceSensor::Modis);

    expect($result['reason'])->toBe('range_too_long')
        ->and($result['median'])->toBeNull();
});

it('defaults to the last thirty days of the record', function () {
    expect(app(PeriodComparison::class)->defaultWindow('2026-10-28'))->toBe(['2026-09-29', '2026-10-28'])
        ->and(app(PeriodComparison::class)->defaultWindow(null))->toBeNull();
});

it('transfers the other sensor onto the reference scale when the reference did not fly', function () {
    $region = Region::factory()->preset()->create();
    CalibrationFactor::factory()->for($region)->create(['value' => 0.25, 'ci_low' => 0.2, 'ci_high' => 0.3]);
    CalendarEntry::factory()->forRegion($region)->daily('2025-09-02', Instrument::Viirs, 40)->create();

    $result = app(PeriodComparison::class)->build($region, '2025-09-01', '2025-09-10', HarmonizationMethod::Ratio, ReferenceSensor::Modis);

    expect($result['current'])->toBe(10.0)
        ->and($result['hotspots'])->toBe(160);
});
