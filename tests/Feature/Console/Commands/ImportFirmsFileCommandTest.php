<?php

use App\Models\Detection;
use App\Models\IngestRun;

it('imports a FIRMS CSV file and reports the counts', function () {
    $this->artisan('firms:import', ['path' => base_path('tests/Fixtures/viirs_sample.csv')])
        ->expectsOutputToContain('Importing')
        ->assertSuccessful();

    expect(Detection::query()->count())->toBe(3)
        ->and(IngestRun::query()->sole()->source)->toBe('file:viirs_sample.csv');
});

it('clips to a bounding box and labels the run', function () {
    $this->artisan('firms:import', [
        'path' => base_path('tests/Fixtures/viirs_sample.csv'),
        '--bbox' => '0,20,20,40',
        '--source' => 'test:north-africa',
    ])->assertSuccessful();

    expect(Detection::query()->count())->toBe(2)
        ->and(IngestRun::query()->sole()->source)->toBe('test:north-africa');
});

it('fails when the file does not exist', function () {
    $this->artisan('firms:import', ['path' => '/tmp/missing-firms-file.csv'])
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});
