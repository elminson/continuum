<?php

use App\Enums\FirmsSource;
use App\Exceptions\FirmsException;
use App\Services\Firms\AreaApiClient;
use App\Support\Geo\BoundingBox;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('continuum.firms.map_key', 'test-key');
    config()->set('cache.default', 'array');
    Cache::flush();
});

it('pauses every request when FIRMS reports the transaction limit was exceeded', function () {
    Http::fake([
        '*/mapserver/mapkey_status/*' => Http::response(['transaction_limit' => 5000, 'current_transactions' => 10, 'transaction_interval' => '10 minutes']),
        '*/api/area/csv/*' => Http::response('Exceeding allowed transaction limit.', 400),
    ]);
    $client = app(AreaApiClient::class);
    $box = BoundingBox::fromString('-71,17,-67,20');

    $first = fn () => $client->fetchCsv(FirmsSource::ModisSp, $box, 5, '2026-01-01');

    expect($first)->toThrow(fn (FirmsException $e) => expect($e->rateLimited)->toBeTrue())
        ->and($client->pausedFor())->toBeGreaterThan(0)
        ->and($client->budgetResetsIn())->toBeGreaterThanOrEqual($client->pausedFor());

    // A second call during the pause never reaches FIRMS.
    expect($first)->toThrow(FirmsException::class);
    Http::assertSentCount(2);
});

it('stops just short of the limit using the key status reported by FIRMS', function () {
    Http::fake([
        '*/mapserver/mapkey_status/*' => Http::response(['transaction_limit' => 5000, 'current_transactions' => 4900, 'transaction_interval' => '10 minutes']),
        '*/api/area/csv/*' => Http::response("latitude,longitude\n", 200),
    ]);
    $client = app(AreaApiClient::class);

    expect(fn () => $client->fetchCsv(FirmsSource::ModisSp, BoundingBox::fromString('-71,17,-67,20'), 5, '2026-01-01'))
        ->toThrow(fn (FirmsException $e) => expect($e->rateLimited)->toBeTrue());
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/area/csv/'));
});

it('treats an invalid key as invalid when the budget is not spent', function () {
    Http::fake([
        '*/mapserver/mapkey_status/*' => Http::response(['transaction_limit' => 5000, 'current_transactions' => 10, 'transaction_interval' => '10 minutes']),
        '*/api/area/csv/*' => Http::response('Invalid MAP_KEY.', 400),
    ]);

    expect(fn () => app(AreaApiClient::class)->fetchCsv(FirmsSource::ModisSp, BoundingBox::fromString('-71,17,-67,20'), 5, '2026-01-01'))
        ->toThrow(fn (FirmsException $e) => expect($e->rateLimited)->toBeFalse()->and($e->getMessage())->toContain('rejected the MAP_KEY'));
});
