<?php

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * Roughly equal-area global grid keyed by integer cell ids.
 *
 * Rows are latitude bands of constant height (cellKm). Each row is divided
 * into as many columns as fit around the parallel at the row's centre, so
 * cells stay close to cellKm x cellKm everywhere instead of shrinking toward
 * the poles as plain lat/lon cells do. Cell ids pack (row, column) into one
 * 64-bit integer so they can be indexed and grouped cheaply in SQL.
 */
final readonly class Grid
{
    public const float EARTH_RADIUS_KM = 6371.0088;

    public const float KM_PER_DEGREE = 111.319491;

    private const int COLUMN_BITS = 20;

    private const int COLUMN_MASK = (1 << self::COLUMN_BITS) - 1;

    public float $latitudeStep;

    public int $rows;

    public function __construct(public float $cellKm)
    {
        if ($cellKm <= 0 || $cellKm > 500) {
            throw new InvalidArgumentException('Grid cell size must be between 0 and 500 km.');
        }

        $this->latitudeStep = $cellKm / self::KM_PER_DEGREE;
        $this->rows = (int) ceil(180 / $this->latitudeStep);
    }

    public static function fromConfig(): self
    {
        return new self((float) config('continuum.grid_km', 1));
    }

    public function rowFor(float $latitude): int
    {
        $row = (int) floor(($latitude + 90) / $this->latitudeStep);

        return max(0, min($this->rows - 1, $row));
    }

    public function rowCenterLatitude(int $row): float
    {
        return -90 + ($row + 0.5) * $this->latitudeStep;
    }

    public function columnsForRow(int $row): int
    {
        $latitude = max(-89.999, min(89.999, $this->rowCenterLatitude($row)));
        $columns = (int) ceil(360 * cos(deg2rad($latitude)) / $this->latitudeStep);

        return max(1, min(self::COLUMN_MASK, $columns));
    }

    public function columnFor(int $row, float $longitude): int
    {
        $columns = $this->columnsForRow($row);
        $column = (int) floor(($longitude + 180) / (360 / $columns));

        return max(0, min($columns - 1, $column));
    }

    public function cellIdFor(float $latitude, float $longitude): int
    {
        $row = $this->rowFor($latitude);

        return ($row << self::COLUMN_BITS) | $this->columnFor($row, $longitude);
    }

    public function rowOf(int $cellId): int
    {
        return $cellId >> self::COLUMN_BITS;
    }

    public function columnOf(int $cellId): int
    {
        return $cellId & self::COLUMN_MASK;
    }

    /**
     * @return array{west: float, south: float, east: float, north: float}
     */
    public function cellBounds(int $cellId): array
    {
        $row = $this->rowOf($cellId);
        $column = $this->columnOf($cellId);
        $columnWidth = 360 / $this->columnsForRow($row);

        return [
            'west' => -180 + $column * $columnWidth,
            'south' => -90 + $row * $this->latitudeStep,
            'east' => -180 + ($column + 1) * $columnWidth,
            'north' => -90 + ($row + 1) * $this->latitudeStep,
        ];
    }

    /**
     * @return array{lat: float, lon: float}
     */
    public function cellCenter(int $cellId): array
    {
        $bounds = $this->cellBounds($cellId);

        return [
            'lat' => ($bounds['south'] + $bounds['north']) / 2,
            'lon' => ($bounds['west'] + $bounds['east']) / 2,
        ];
    }

    public function nominalCellAreaKm2(): float
    {
        return $this->cellKm ** 2;
    }
}
