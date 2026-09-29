<?php

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * Plain latitude/longitude grid (e.g. 0.25 degrees) used for calendar
 * heatmaps and map density tiles, where equal area matters less than
 * predictable, rectangular cells.
 */
final readonly class LatLonGrid
{
    public int $columns;

    public function __construct(public float $degrees)
    {
        if ($degrees <= 0 || $degrees > 90) {
            throw new InvalidArgumentException('Cell size must be between 0 and 90 degrees.');
        }

        $this->columns = (int) ceil(360 / $degrees);
    }

    public static function fromConfig(): self
    {
        return new self((float) config('continuum.calendar_cell_deg', 0.25));
    }

    public function cellIdFor(float $latitude, float $longitude): int
    {
        $row = (int) floor(($latitude + 90) / $this->degrees);
        $column = (int) floor(($longitude + 180) / $this->degrees);
        $row = max(0, min((int) ceil(180 / $this->degrees) - 1, $row));
        $column = max(0, min($this->columns - 1, $column));

        return $row * $this->columns + $column;
    }

    /**
     * @return array{west: float, south: float, east: float, north: float}
     */
    public function cellBounds(int $cellId): array
    {
        $row = intdiv($cellId, $this->columns);
        $column = $cellId % $this->columns;

        return [
            'west' => -180 + $column * $this->degrees,
            'south' => -90 + $row * $this->degrees,
            'east' => -180 + ($column + 1) * $this->degrees,
            'north' => -90 + ($row + 1) * $this->degrees,
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
}
