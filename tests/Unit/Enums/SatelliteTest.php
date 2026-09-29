<?php

use App\Enums\Instrument;
use App\Enums\Satellite;

it('accepts every FIRMS spelling of a platform', function (string $code, Satellite $expected) {
    expect(Satellite::fromFirms($code))->toBe($expected);
})->with([
    'T' => ['T', Satellite::Terra],
    'Terra' => ['Terra', Satellite::Terra],
    'A' => ['A', Satellite::Aqua],
    'aqua lowercase' => ['aqua', Satellite::Aqua],
    'N' => ['N', Satellite::SuomiNpp],
    'NPP' => ['NPP', Satellite::SuomiNpp],
    'N20' => ['N20', Satellite::Noaa20],
    'NOAA-20' => ['NOAA-20', Satellite::Noaa20],
    'digit 1' => ['1', Satellite::Noaa20],
    'N21' => ['N21', Satellite::Noaa21],
    'padded' => [' n21 ', Satellite::Noaa21],
]);

it('rejects unknown platform codes', function () {
    Satellite::fromFirms('X9');
})->throws(InvalidArgumentException::class, 'Unknown FIRMS satellite code');

test('platforms map to their instrument and afternoon orbit', function () {
    expect(Satellite::Terra->instrument())->toBe(Instrument::Modis)
        ->and(Satellite::Terra->isAfternoonOrbit())->toBeFalse()
        ->and(Satellite::Aqua->isAfternoonOrbit())->toBeTrue()
        ->and(Satellite::Noaa21->instrument())->toBe(Instrument::Viirs)
        ->and(Satellite::forInstrument(Instrument::Viirs))->toBe([Satellite::SuomiNpp, Satellite::Noaa20, Satellite::Noaa21]);
});
