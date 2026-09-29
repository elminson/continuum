<?php

namespace App\Data;

use App\Enums\ConfidenceClass;
use App\Enums\DayNight;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Support\Geo\Grid;
use App\Support\Geo\LatLonGrid;
use Carbon\CarbonImmutable;

/**
 * One active-fire pixel as read from a FIRMS CSV row.
 */
final readonly class DetectionData
{
    public function __construct(
        public Instrument $instrument,
        public Satellite $satellite,
        public CarbonImmutable $acquiredAt,
        public float $latitude,
        public float $longitude,
        public float $brightness,
        public ?float $brightnessSecondary,
        public float $scan,
        public float $track,
        public float $frp,
        public string $confidenceRaw,
        public ConfidenceClass $confidence,
        public DayNight $dayNight,
        public string $version,
    ) {}

    /**
     * Stable identity for idempotent ingestion across overlapping feeds.
     */
    public function fingerprint(): string
    {
        return md5(implode('|', [
            $this->satellite->value,
            $this->acquiredAt->format('Y-m-d H:i'),
            number_format($this->latitude, 5, '.', ''),
            number_format($this->longitude, 5, '.', ''),
        ]));
    }

    /**
     * Nominal pixel footprint on the ground (km²) from scan and track sizes.
     */
    public function footprintKm2(): float
    {
        return $this->scan * $this->track;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(Grid $grid, LatLonGrid $calendarGrid, int $ingestRunId): array
    {
        return [
            'fingerprint' => $this->fingerprint(),
            'ingest_run_id' => $ingestRunId,
            'instrument' => $this->instrument->value,
            'satellite' => $this->satellite->value,
            'acquired_at' => $this->acquiredAt->format('Y-m-d H:i:s'),
            'acq_date' => $this->acquiredAt->toDateString(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'brightness' => $this->brightness,
            'brightness_secondary' => $this->brightnessSecondary,
            'scan' => $this->scan,
            'track' => $this->track,
            'footprint_km2' => round($this->footprintKm2(), 4),
            'frp' => $this->frp,
            'confidence_raw' => $this->confidenceRaw,
            'confidence' => $this->confidence->value,
            'daynight' => $this->dayNight->value,
            'version' => $this->version,
            'cell_id' => $grid->cellIdFor($this->latitude, $this->longitude),
            'calendar_cell_id' => $calendarGrid->cellIdFor($this->latitude, $this->longitude),
        ];
    }
}
