<?php

use App\Enums\CalendarPeriod;
use App\Enums\ReferenceSensor;
use App\Models\Alert;
use App\Models\Region;
use App\Support\AlertMessage;
use Carbon\CarbonImmutable;

it('keeps the stored English wording', function () {
    expect(AlertMessage::describe(CalendarPeriod::Week, CarbonImmutable::parse('2026-09-07'), 1234, 400, 4.06, 14, ReferenceSensor::Modis, 'en'))
        ->toBe('Week of 7 Sep 2026: 1,234 MODIS-equivalent cell-days, 3.1x the median of 14 other years (z = 4.1).')
        ->and(AlertMessage::describe(CalendarPeriod::Month, CarbonImmutable::parse('2026-09-01'), 50, 0, 3, 9, ReferenceSensor::Viirs, 'en'))
        ->toBe('September 2026: 50 VIIRS-equivalent cell-days, far above the median of 9 other years (z = 3.0).');
});

it('writes the message in Spanish with Spanish numbers and dates', function () {
    expect(AlertMessage::describe(CalendarPeriod::Week, CarbonImmutable::parse('2026-09-07'), 1234, 400, 4.06, 14, ReferenceSensor::Modis, 'es'))
        ->toBe('Semana del 7 sep. 2026: 1.234 celdas-día equivalentes a MODIS, 3,1x la mediana de otros 14 años (z = 4,1).');
});

it('serves alerts in the reader language while storing English', function () {
    $region = Region::factory()->preset()->create(['slug' => 'gran-chaco']);
    $alert = Alert::factory()->for($region)->create([
        'period' => CalendarPeriod::Month,
        'period_start' => '2026-09-01',
        'observed' => 900,
        'expected' => 300,
        'z_score' => 3.4,
        'climatology_years' => 12,
        'reference' => ReferenceSensor::Modis,
        'message' => 'stored english',
    ]);

    $this->getJson('/api/v1/regions/gran-chaco/alerts')->assertJsonPath('data.0.message', 'stored english');

    $this->withHeader('Accept-Language', 'es')
        ->getJson('/api/v1/regions/gran-chaco/alerts')
        ->assertJsonPath('data.0.message', 'septiembre 2026: 900 celdas-día equivalentes a MODIS, 3x la mediana de otros 12 años (z = 3,4).');

    expect($alert->fresh()->getRawOriginal('message'))->toBe('stored english');
});
