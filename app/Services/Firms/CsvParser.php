<?php

namespace App\Services\Firms;

use App\Data\DetectionData;
use App\Enums\ConfidenceClass;
use App\Enums\DayNight;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Generator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Streams FIRMS CSV files (public feeds, Area API responses and archive
 * downloads, plain or gzip) into DetectionData objects.
 */
class CsvParser
{
    public int $rowsRead = 0;

    public int $rowsSkipped = 0;

    /**
     * FIRMS "type" values other than 0 flag volcanoes, static land sources
     * and offshore detections, which are not burning activity.
     */
    public function __construct(public bool $includeStaticSources = false) {}

    /**
     * @return Generator<int, DetectionData>
     */
    public function parseFile(string $path, ?BoundingBox $clip = null): Generator
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("CSV file not found: {$path}");
        }

        $stream = str_ends_with(strtolower($path), '.gz')
            ? fopen('compress.zlib://'.$path, 'rb')
            : fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open {$path}");
        }

        try {
            yield from $this->parseStream($stream, $clip);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return Generator<int, DetectionData>
     */
    public function parseString(string $csv, ?BoundingBox $clip = null): Generator
    {
        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to allocate a memory stream.');
        }

        fwrite($stream, $csv);
        rewind($stream);

        try {
            yield from $this->parseStream($stream, $clip);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return Generator<int, DetectionData>
     */
    public function parseStream($stream, ?BoundingBox $clip = null): Generator
    {
        $this->rowsRead = 0;
        $this->rowsSkipped = 0;

        $header = fgetcsv($stream, escape: '');

        if ($header === false || $header === [null]) {
            return;
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
        $index = array_flip($header);
        $instrument = Instrument::fromHeader($header);
        $columns = $instrument->csvColumns();

        foreach (['latitude', 'longitude', 'acq_date', 'acq_time', 'satellite', 'confidence', 'frp', 'scan', 'track', $columns['brightness']] as $required) {
            if (! array_key_exists($required, $index)) {
                throw new InvalidArgumentException("CSV is missing the required column [{$required}].");
            }
        }

        while (($row = fgetcsv($stream, escape: '')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $this->rowsRead++;

            $detection = $this->toDetection($row, $index, $instrument, $columns);

            if ($detection === null) {
                $this->rowsSkipped++;

                continue;
            }

            if ($clip !== null && ! $clip->contains($detection->latitude, $detection->longitude)) {
                $this->rowsSkipped++;

                continue;
            }

            yield $detection;
        }
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $index
     * @param  array{brightness: string, secondary: string}  $columns
     */
    private function toDetection(array $row, array $index, Instrument $instrument, array $columns): ?DetectionData
    {
        $value = fn (string $column): ?string => isset($index[$column]) ? ($row[$index[$column]] ?? null) : null;

        try {
            if (! $this->includeStaticSources && ($type = $value('type')) !== null && $type !== '' && (int) $type !== 0) {
                return null;
            }

            $latitude = (float) $value('latitude');
            $longitude = (float) $value('longitude');

            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                return null;
            }

            $time = str_pad((string) (int) $value('acq_time'), 4, '0', STR_PAD_LEFT);
            $acquiredAt = CarbonImmutable::createFromFormat('Y-m-d Hi', $value('acq_date').' '.$time, 'UTC');

            if ($acquiredAt === null) {
                return null;
            }

            $confidenceRaw = trim((string) $value('confidence'));
            $rowInstrument = $instrument;

            if (($declared = $value('instrument')) !== null && $declared !== '') {
                $rowInstrument = strtoupper($declared) === 'VIIRS' ? Instrument::Viirs : Instrument::Modis;
            }

            $secondary = $value($columns['secondary']);

            return new DetectionData(
                instrument: $rowInstrument,
                satellite: Satellite::fromFirms((string) $value('satellite')),
                acquiredAt: $acquiredAt,
                latitude: round($latitude, 5),
                longitude: round($longitude, 5),
                brightness: (float) $value($columns['brightness']),
                brightnessSecondary: $secondary === null || $secondary === '' ? null : (float) $secondary,
                scan: max(0.01, (float) $value('scan')),
                track: max(0.01, (float) $value('track')),
                frp: max(0.0, (float) $value('frp')),
                confidenceRaw: $confidenceRaw,
                confidence: ConfidenceClass::fromRaw($rowInstrument, $confidenceRaw),
                dayNight: DayNight::fromFirms((string) ($value('daynight') ?? 'D')),
                version: (string) ($value('version') ?? ''),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
