<?php

use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Services\Harmonization\CriticalPeriods;

function monthlyYear(Region $region, int $year, array $values): void
{
    foreach ($values as $index => $value) {
        CalendarEntry::factory()->forRegion($region)->monthly(sprintf('%d-%02d-01', $year, $index + 1), Instrument::Modis, $value)->create();
    }
}

it('finds the season around the peak month and its onset', function () {
    $region = Region::factory()->preset()->create();
    monthlyYear($region, 2022, [5, 5, 5, 5, 10, 20, 60, 120, 200, 110, 40, 10]);
    monthlyYear($region, 2023, [5, 5, 5, 5, 10, 20, 60, 130, 210, 100, 40, 10]);

    $result = app(CriticalPeriods::class)->forRegion($region);

    expect($result['years'])->toBe(2)
        ->and($result['peak']['label'])->toBe('Sep')
        ->and($result['peak']['typical'])->toBe(205.0)
        ->and($result['season'])->toMatchArray(['start_label' => 'Aug', 'end_label' => 'Oct', 'length' => 3, 'onset_label' => 'Jul'])
        ->and($result['climatology'])->toHaveCount(12)
        ->and($result['climatology'][7])->toMatchArray(['label' => 'Aug', 'typical' => 125.0, 'max' => 130.0, 'years' => 2])
        ->and($result['latest'])->toMatchArray(['period_start' => '2023-12-01', 'index' => 12, 'in_season' => false]);
});

it('wraps the season around the new year', function () {
    $region = Region::factory()->preset()->create();
    monthlyYear($region, 2023, [200, 120, 30, 5, 5, 5, 5, 5, 5, 20, 60, 150]);

    $result = app(CriticalPeriods::class)->forRegion($region);

    expect($result['peak']['label'])->toBe('Jan')
        ->and($result['season'])->toMatchArray(['start' => 12, 'end' => 2, 'start_label' => 'Dec', 'end_label' => 'Feb', 'length' => 3, 'onset_label' => 'Nov'])
        ->and($result['latest']['in_season'])->toBeTrue();
});

it('returns no season for a region without data', function () {
    $region = Region::factory()->preset()->create();

    $result = app(CriticalPeriods::class)->forRegion($region);

    expect($result['years'])->toBe(0)
        ->and($result['peak'])->toBeNull()
        ->and($result['season'])->toBeNull()
        ->and($result['latest'])->toBeNull();
});
