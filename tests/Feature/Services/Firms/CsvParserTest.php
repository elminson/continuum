<?php

use App\Data\DetectionData;
use App\Enums\ConfidenceClass;
use App\Enums\DayNight;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Services\Firms\CsvParser;
use App\Support\Geo\BoundingBox;

it('parses MODIS public feed rows into detections', function () {
    $parser = new CsvParser;

    $detections = iterator_to_array($parser->parseFile(base_path('tests/Fixtures/modis_sample.csv')), false);

    expect($detections)->toHaveCount(3)
        ->and($parser->rowsRead)->toBe(3)
        ->and($parser->rowsSkipped)->toBe(0);

    $first = $detections[0];

    expect($first)->toBeInstanceOf(DetectionData::class)
        ->and($first->instrument)->toBe(Instrument::Modis)
        ->and($first->satellite)->toBe(Satellite::Terra)
        ->and($first->acquiredAt->format('Y-m-d H:i e'))->toBe('2026-09-24 00:35 UTC')
        ->and($first->latitude)->toBe(-29.50686)
        ->and($first->longitude)->toBe(-60.74675)
        ->and($first->brightness)->toBe(300.33)
        ->and($first->brightnessSecondary)->toBe(284.46)
        ->and($first->frp)->toBe(15.0)
        ->and($first->confidenceRaw)->toBe('19')
        ->and($first->confidence)->toBe(ConfidenceClass::Low)
        ->and($first->dayNight)->toBe(DayNight::Night)
        ->and($first->version)->toBe('6.1NRT')
        ->and($first->footprintKm2())->toBe(1.97 * 1.37);

    expect($detections[2]->satellite)->toBe(Satellite::Aqua)
        ->and($detections[2]->confidence)->toBe(ConfidenceClass::High)
        ->and($detections[2]->dayNight)->toBe(DayNight::Day)
        ->and($detections[2]->acquiredAt->format('H:i'))->toBe('17:40');
});

it('parses VIIRS public feed rows with word confidence values', function () {
    $parser = new CsvParser;

    $detections = iterator_to_array($parser->parseFile(base_path('tests/Fixtures/viirs_sample.csv')), false);

    expect($detections)->toHaveCount(3)
        ->and($detections[0]->instrument)->toBe(Instrument::Viirs)
        ->and($detections[0]->satellite)->toBe(Satellite::SuomiNpp)
        ->and($detections[0]->brightness)->toBe(299.42)
        ->and($detections[0]->brightnessSecondary)->toBe(278.94)
        ->and($detections[0]->confidence)->toBe(ConfidenceClass::Nominal)
        ->and($detections[1]->confidence)->toBe(ConfidenceClass::Low)
        ->and($detections[2]->satellite)->toBe(Satellite::Noaa20)
        ->and($detections[2]->confidence)->toBe(ConfidenceClass::High)
        ->and($detections[2]->acquiredAt->format('Y-m-d H:i'))->toBe('2026-09-24 00:01');
});

it('keeps only detections inside the clip box and counts the rest as skipped', function () {
    $parser = new CsvParser;

    $detections = iterator_to_array($parser->parseFile(
        base_path('tests/Fixtures/modis_sample.csv'),
        new BoundingBox(-70, -35, -55, -15),
    ), false);

    expect($detections)->toHaveCount(2)
        ->and($parser->rowsRead)->toBe(3)
        ->and($parser->rowsSkipped)->toBe(1);
});

it('drops static sources when an archive file carries a type column', function () {
    $csv = implode("\n", [
        'latitude,longitude,brightness,scan,track,acq_date,acq_time,satellite,instrument,confidence,version,bright_t31,frp,daynight,type',
        '-29.5,-60.7,300.3,1.9,1.3,2020-08-01,1530,Terra,MODIS,60,6.1,284.4,15,D,0',
        '-29.6,-60.8,300.3,1.9,1.3,2020-08-01,1530,Aqua,MODIS,60,6.1,284.4,15,D,1',
        '-29.7,-60.9,300.3,1.9,1.3,2020-08-01,1530,Aqua,MODIS,60,6.1,284.4,15,D,2',
    ]);

    $parser = new CsvParser;
    $detections = iterator_to_array($parser->parseString($csv), false);

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->satellite)->toBe(Satellite::Terra)
        ->and($parser->rowsSkipped)->toBe(2);

    $inclusive = new CsvParser(includeStaticSources: true);

    expect(iterator_to_array($inclusive->parseString($csv), false))->toHaveCount(3);
});

it('skips rows with impossible coordinates or unknown platforms', function () {
    $csv = implode("\n", [
        'latitude,longitude,brightness,scan,track,acq_date,acq_time,satellite,confidence,version,bright_t31,frp,daynight',
        '999,-60.7,300.3,1.9,1.3,2020-08-01,1530,T,60,6.1NRT,284.4,15,D',
        '-29.5,-60.7,300.3,1.9,1.3,2020-08-01,1530,ZZ,60,6.1NRT,284.4,15,D',
        '-29.5,-60.7,300.3,1.9,1.3,2020-08-01,1530,T,60,6.1NRT,284.4,15,D',
    ]);

    $parser = new CsvParser;
    $detections = iterator_to_array($parser->parseString($csv), false);

    expect($detections)->toHaveCount(1)
        ->and($parser->rowsRead)->toBe(3)
        ->and($parser->rowsSkipped)->toBe(2);
});

it('reads gzip compressed files', function () {
    $path = tempnam(sys_get_temp_dir(), 'firms').'.csv.gz';
    file_put_contents($path, gzencode((string) file_get_contents(base_path('tests/Fixtures/viirs_sample.csv'))));

    try {
        $detections = iterator_to_array((new CsvParser)->parseFile($path), false);
    } finally {
        @unlink($path);
    }

    expect($detections)->toHaveCount(3);
});

it('rejects files that lack a required column', function () {
    $csv = "latitude,longitude,brightness\n-29.5,-60.7,300.3\n";

    iterator_to_array((new CsvParser)->parseString($csv), false);
})->throws(InvalidArgumentException::class, 'missing the required column');

it('rejects missing files', function () {
    iterator_to_array((new CsvParser)->parseFile('/nonexistent/firms.csv'), false);
})->throws(InvalidArgumentException::class, 'CSV file not found');
