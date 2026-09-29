<?php

use App\Exceptions\FirmsException;
use App\Models\Detection;
use App\Models\IngestRun;
use Illuminate\Support\Facades\Http;

it('downloads the MODIS feed and imports its rows', function () {
    Http::preventStrayRequests();
    Http::fake([
        'firms.modaps.eosdis.nasa.gov/data/active_fire/modis-c6.1/csv/MODIS_C6_1_Global_24h.csv' => Http::response(
            (string) file_get_contents(base_path('tests/Fixtures/modis_sample.csv')),
        ),
    ]);

    $this->artisan('firms:ingest-public', ['--feed' => ['modis'], '--window' => '24h'])
        ->assertSuccessful();

    expect(Detection::query()->count())->toBe(3)
        ->and(IngestRun::query()->sole()->source)->toBe('public:modis:24h');

    Http::assertSentCount(1);
});

it('clips to the given box', function () {
    Http::preventStrayRequests();
    Http::fake([
        'firms.modaps.eosdis.nasa.gov/*' => Http::response((string) file_get_contents(base_path('tests/Fixtures/modis_sample.csv'))),
    ]);

    $this->artisan('firms:ingest-public', ['--feed' => ['modis'], '--window' => '48h', '--bbox' => '-70,-35,-55,-15'])
        ->assertSuccessful();

    expect(Detection::query()->count())->toBe(2);
});

it('rejects an unknown window without downloading', function () {
    Http::preventStrayRequests();
    Http::fake();

    $this->artisan('firms:ingest-public', ['--window' => '30d'])
        ->expectsOutputToContain('Window must be 24h, 48h or 7d')
        ->assertExitCode(2);

    Http::assertNothingSent();
});

it('fails when the feed does not look like a FIRMS CSV', function () {
    Http::preventStrayRequests();
    Http::fake(['firms.modaps.eosdis.nasa.gov/*' => Http::response('<html>maintenance</html>')]);

    $this->artisan('firms:ingest-public', ['--feed' => ['viirs-snpp']])->assertFailed();

    expect(Detection::query()->count())->toBe(0);
})->throws(FirmsException::class);
