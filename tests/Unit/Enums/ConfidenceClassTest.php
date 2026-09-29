<?php

use App\Enums\ConfidenceClass;
use App\Enums\Instrument;

it('classifies MODIS confidence at the FIRMS boundaries', function (int $confidence, ConfidenceClass $expected) {
    expect(ConfidenceClass::fromModis($confidence))->toBe($expected);
})->with([
    '29 is low' => [29, ConfidenceClass::Low],
    '30 is nominal' => [30, ConfidenceClass::Nominal],
    '79 is nominal' => [79, ConfidenceClass::Nominal],
    '80 is high' => [80, ConfidenceClass::High],
    '100 is high' => [100, ConfidenceClass::High],
]);

it('accepts VIIRS letters and words', function (string $raw, ConfidenceClass $expected) {
    expect(ConfidenceClass::fromViirs($raw))->toBe($expected);
})->with([
    'l' => ['l', ConfidenceClass::Low],
    'low' => ['low', ConfidenceClass::Low],
    'n' => ['n', ConfidenceClass::Nominal],
    'h' => ['h', ConfidenceClass::High],
    'HIGH' => ['HIGH', ConfidenceClass::High],
    'unknown falls back to nominal' => ['?', ConfidenceClass::Nominal],
]);

it('dispatches on the instrument when given a raw value', function () {
    expect(ConfidenceClass::fromRaw(Instrument::Modis, '85'))->toBe(ConfidenceClass::High)
        ->and(ConfidenceClass::fromRaw(Instrument::Viirs, 'l'))->toBe(ConfidenceClass::Low);
});
