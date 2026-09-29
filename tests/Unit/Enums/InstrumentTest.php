<?php

use App\Enums\Instrument;

it('detects the instrument from the CSV header', function () {
    expect(Instrument::fromHeader(['latitude', 'longitude', 'bright_ti4', 'scan']))->toBe(Instrument::Viirs)
        ->and(Instrument::fromHeader(['latitude', 'longitude', 'brightness', 'scan']))->toBe(Instrument::Modis);
});

test('nominal pixel areas differ by about seven times', function () {
    expect(Instrument::Modis->nominalPixelAreaKm2() / Instrument::Viirs->nominalPixelAreaKm2())->toBeBetween(7.0, 7.2);
});

test('the brightness columns follow the FIRMS file layout', function () {
    expect(Instrument::Modis->csvColumns())->toBe(['brightness' => 'brightness', 'secondary' => 'bright_t31'])
        ->and(Instrument::Viirs->csvColumns())->toBe(['brightness' => 'bright_ti4', 'secondary' => 'bright_ti5']);
});
