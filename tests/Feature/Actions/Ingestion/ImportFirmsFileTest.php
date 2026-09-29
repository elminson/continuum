<?php

use App\Actions\Ingestion\ImportFirmsFile;
use App\Enums\IngestStatus;
use App\Enums\Instrument;
use App\Models\Detection;
use App\Models\IngestRun;
use App\Support\Geo\BoundingBox;

it('stores every detection of a file once and records the run', function () {
    $import = app(ImportFirmsFile::class);

    $summary = $import(base_path('tests/Fixtures/modis_sample.csv'), 'file:modis_sample.csv');

    expect($summary->read)->toBe(3)
        ->and($summary->inserted)->toBe(3)
        ->and($summary->skipped)->toBe(0)
        ->and($summary->dateFrom)->toBe('2026-09-24')
        ->and($summary->dateTo)->toBe('2026-09-25');

    $run = IngestRun::query()->sole();

    expect($run->status)->toBe(IngestStatus::Completed)
        ->and($run->source)->toBe('file:modis_sample.csv')
        ->and($run->instrument)->toBe(Instrument::Modis)
        ->and($run->rows_read)->toBe(3)
        ->and($run->rows_inserted)->toBe(3)
        ->and($run->date_from->toDateString())->toBe('2026-09-24')
        ->and($run->date_to->toDateString())->toBe('2026-09-25')
        ->and($run->finished_at)->not->toBeNull();

    $detection = Detection::query()->where('satellite', 'A')->sole();

    expect($detection->ingest_run_id)->toBe($run->id)
        ->and($detection->acq_date->toDateString())->toBe('2026-09-25')
        ->and($detection->frp)->toBe(42.5)
        ->and($detection->footprint_km2)->toBe(1.155)
        ->and($detection->cell_id)->toBeGreaterThan(0);
});

it('does not insert rows again when the same file is imported twice', function () {
    $import = app(ImportFirmsFile::class);
    $import(base_path('tests/Fixtures/modis_sample.csv'), 'file:first');

    $summary = $import(base_path('tests/Fixtures/modis_sample.csv'), 'file:second');

    expect($summary->inserted)->toBe(0)
        ->and($summary->duplicates())->toBe(3)
        ->and(Detection::query()->count())->toBe(3)
        ->and(IngestRun::query()->where('source', 'file:second')->sole()->rows_inserted)->toBe(0);
});

it('records clipped rows as skipped on the run', function () {
    $import = app(ImportFirmsFile::class);

    $summary = $import(base_path('tests/Fixtures/modis_sample.csv'), 'file:clipped', new BoundingBox(-70, -35, -55, -15));

    $run = IngestRun::query()->sole();

    expect($summary->inserted)->toBe(2)
        ->and($summary->skipped)->toBe(1)
        ->and($run->rows_read)->toBe(3)
        ->and($run->rows_skipped)->toBe(1)
        ->and($run->meta['clip'])->toEqual([-70, -35, -55, -15]);
});
