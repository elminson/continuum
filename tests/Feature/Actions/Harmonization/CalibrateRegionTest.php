<?php

use App\Actions\Harmonization\CalibrateRegion;
use App\Enums\HarmonizationMethod;
use App\Enums\Instrument;
use App\Enums\ReferenceSensor;
use App\Models\CalendarEntry;
use App\Models\CalibrationFactor;
use App\Models\Region;

function overlapDays(Region $region, array $modisCounts, string $firstDate = '2024-06-01'): void
{
    foreach ($modisCounts as $offset => $modis) {
        $date = date('Y-m-d', strtotime("{$firstDate} +{$offset} days"));
        CalendarEntry::factory()->forRegion($region)->daily($date, Instrument::Modis, $modis)->create();
        CalendarEntry::factory()->forRegion($region)->daily($date, Instrument::Viirs, $modis * 4)->create();
    }
}

it('fits a MODIS per VIIRS ratio of one quarter when VIIRS sees four times as many cell-days', function () {
    $region = Region::factory()->preset()->create();
    overlapDays($region, [10, 40, 25, 60, 15, 30, 45, 20, 35, 50, 12, 28]);

    $results = app(CalibrateRegion::class)($region);

    $ratio = collect($results)->firstWhere('method', HarmonizationMethod::Ratio->value);

    expect($ratio['status'])->toBe('ok')
        ->and($ratio['reference'])->toBe('modis')
        ->and($ratio['factor'])->toBe(0.25)
        ->and($ratio['ci'][0])->toBeLessThanOrEqual(0.25)
        ->and($ratio['ci'][1])->toBeGreaterThanOrEqual(0.25)
        ->and($ratio['overlap_days'])->toBe(12)
        ->and($ratio['validation']['train_days'])->toBe(9)
        ->and($ratio['validation']['test_days'])->toBe(3)
        ->and($ratio['validation']['mae_improvement'])->toBeGreaterThan(0)
        ->and($ratio['validation']['naive']['mae'])->toBeGreaterThan($ratio['validation']['harmonized']['mae']);

    $stored = CalibrationFactor::query()
        ->where('region_id', $region->id)
        ->where('method', HarmonizationMethod::Ratio->value)
        ->where('reference', ReferenceSensor::Modis->value)
        ->where('month', CalibrationFactor::ALL_YEAR)
        ->sole();

    expect($stored->value)->toBe(0.25)
        ->and($stored->metric)->toBe('cell_days')
        ->and($stored->sample_days)->toBe(12)
        ->and($stored->overlap_from->toDateString())->toBe('2024-06-01')
        ->and($stored->overlap_to->toDateString())->toBe('2024-06-12')
        ->and($stored->validation['mae_improvement'])->toBeGreaterThan(0)
        ->and($stored->version)->toBe(config('continuum.calibration.method_version'));

    expect(CalibrationFactor::query()->where('region_id', $region->id)->where('method', 'ratio')->where('reference', 'modis')->where('month', 6)->sole()->value)->toBe(0.25)
        ->and(CalibrationFactor::query()->where('region_id', $region->id)->where('method', 'ratio')->where('reference', 'viirs')->where('month', 0)->sole()->value)->toBe(4.0)
        ->and(CalibrationFactor::query()->where('region_id', $region->id)->where('method', HarmonizationMethod::FrpThreshold->value)->where('month', 0)->exists())->toBeTrue();
});

it('reports insufficient overlap and stores nothing below the minimum number of days', function () {
    config()->set('continuum.calibration.min_days', 5);
    $region = Region::factory()->preset()->create();
    overlapDays($region, [10, 20, 30, 40]);

    $results = app(CalibrateRegion::class)($region);

    expect($results)->toBe([[
        'status' => 'insufficient_overlap',
        'overlap_days' => 4,
        'active_days' => 4,
        'required_days' => 5,
    ]])
        ->and(CalibrationFactor::query()->where('region_id', $region->id)->exists())->toBeFalse()
        ->and($region->fresh()->harmonized_at)->toBeNull();
});

it('ignores days observed by only one instrument and days before the VIIRS record', function () {
    $region = Region::factory()->preset()->create();
    overlapDays($region, [10, 20, 30, 40, 50]);
    CalendarEntry::factory()->forRegion($region)->daily('2024-07-01', Instrument::Modis, 500)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2011-07-01', Instrument::Modis, 500)->create();
    CalendarEntry::factory()->forRegion($region)->daily('2011-07-01', Instrument::Viirs, 1)->create();

    $pairs = app(CalibrateRegion::class)->overlapPairs($region);

    expect($pairs)->toHaveCount(5)
        ->and(array_column($pairs, 'date'))->toBe(['2024-06-01', '2024-06-02', '2024-06-03', '2024-06-04', '2024-06-05']);
});

it('replaces earlier factors of the same method and reference on recalibration', function () {
    $region = Region::factory()->preset()->create();
    overlapDays($region, [10, 20, 30, 40, 50, 60]);
    app(CalibrateRegion::class)($region);

    CalendarEntry::query()->where('region_id', $region->id)->where('instrument', 'viirs')->update(['cell_days' => 100]);
    app(CalibrateRegion::class)($region);

    $factors = CalibrationFactor::query()->where('region_id', $region->id)->where('method', 'ratio')->where('reference', 'modis');

    expect($factors->where('month', 0)->count())->toBe(1)
        ->and($factors->where('month', 0)->sole()->value)->toBe(0.35);
});
