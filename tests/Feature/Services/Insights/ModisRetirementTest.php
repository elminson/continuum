<?php

use App\Enums\CalendarPeriod;
use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Services\Insights\ModisRetirement;
use App\Support\Stats\FrpHistogram;
use Illuminate\Support\Carbon;

/**
 * Daily overlap from $from to $to where VIIRS sees $ratio times MODIS.
 */
function retirementOverlap(Region $region, string $from, string $to, int $ratio = 4): void
{
    $rows = [];
    $day = Carbon::parse($from);

    while ($day->lte($to)) {
        $modis = 5 + ($day->dayOfYear % 20);

        foreach ([Instrument::Modis->value => $modis, Instrument::Viirs->value => $modis * $ratio] as $instrument => $cellDays) {
            $rows[] = [
                'region_id' => $region->id,
                'period' => CalendarPeriod::Day->value,
                'period_start' => $day->toDateString(),
                'instrument' => $instrument,
                'detections' => $cellDays,
                'active_cells' => $cellDays,
                'cell_days' => $cellDays,
                'frp_sum' => $cellDays * 10,
                'frp_max' => 50,
                'footprint_km2' => $cellDays,
                'day_detections' => $cellDays,
                'night_detections' => 0,
                'low_confidence' => 0,
                'nominal_confidence' => $cellDays,
                'high_confidence' => 0,
                'active_days' => 1,
                'coverage_days' => 1,
                'frp_histogram' => json_encode(FrpHistogram::empty()),
            ];
        }

        $day = $day->addDay();
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        CalendarEntry::query()->insert($chunk);
    }
}

it('rebuilds the years after a simulated MODIS stop from VIIRS alone', function () {
    config()->set('continuum.calibration.bootstrap_samples', 20);
    $region = Region::factory()->preset()->create();
    retirementOverlap($region, '2014-01-01', '2015-12-31');

    $result = app(ModisRetirement::class)->simulate($region);

    expect($result['available'])->toBeTrue()
        ->and($result['years'])->toBe([2015])
        ->and($result['year'])->toBe(2015)
        ->and($result['cutoff'])->toBe('2015-01-01')
        ->and($result['train_days'])->toBe(365)
        ->and($result['test_days'])->toBe(365)
        ->and($result['factor'])->toBe(0.25)
        ->and($result['total_error'])->toEqual(0)
        ->and($result['mae_improvement'])->toEqual(1)
        ->and($result['monthly_correlation'])->toEqual(1);

    $retired = collect($result['points'])->where('retired', true);
    $context = collect($result['points'])->where('retired', false);

    expect($retired)->toHaveCount(12)
        ->and($context)->toHaveCount(12)
        ->and($context->first()['estimate'])->toBeNull()
        ->and($retired->first()['estimate'])->toEqual($retired->first()['modis']);
});

it('reports that the simulation is unavailable without a year of overlap before the cutoff', function () {
    $region = Region::factory()->preset()->create();
    retirementOverlap($region, '2024-06-01', '2024-09-30');

    expect(app(ModisRetirement::class)->simulate($region))
        ->toBe(['available' => false, 'years' => [], 'year' => null]);
});

it('serves the simulation through the public API', function () {
    config()->set('continuum.calibration.bootstrap_samples', 20);
    $region = Region::factory()->preset()->create(['slug' => 'hispaniola']);
    retirementOverlap($region, '2014-01-01', '2015-12-31');

    $this->getJson('/api/v1/regions/hispaniola/modis-retirement?year=2015')
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.year', 2015)
        ->assertJsonPath('data.factor', 0.25);
});
