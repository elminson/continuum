<?php

namespace App\Support\Geo;

use InvalidArgumentException;

final readonly class BoundingBox
{
    public function __construct(
        public float $west,
        public float $south,
        public float $east,
        public float $north,
    ) {
        if ($west < -180 || $east > 180 || $south < -90 || $north > 90) {
            throw new InvalidArgumentException('Bounding box is outside the valid coordinate range.');
        }

        if ($west >= $east || $south >= $north) {
            throw new InvalidArgumentException('Bounding box must satisfy west < east and south < north.');
        }
    }

    /**
     * Parse "west,south,east,north" as used by the FIRMS Area API.
     */
    public static function fromString(string $value): self
    {
        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 4 || array_filter($parts, fn (string $part) => ! is_numeric($part)) !== []) {
            throw new InvalidArgumentException('Expected four numeric values: west,south,east,north.');
        }

        return new self((float) $parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    public static function fromArray(array $bbox): self
    {
        return new self((float) $bbox[0], (float) $bbox[1], (float) $bbox[2], (float) $bbox[3]);
    }

    public static function world(): self
    {
        return new self(-180, -90, 180, 90);
    }

    public function containsBox(self $other): bool
    {
        return $other->west >= $this->west && $other->east <= $this->east
            && $other->south >= $this->south && $other->north <= $this->north;
    }

    /**
     * Grow the box by the given number of degrees on every side, clamped to
     * the valid coordinate range.
     */
    public function padded(float $degrees): self
    {
        if ($degrees <= 0) {
            return $this;
        }

        return new self(
            max(-180, $this->west - $degrees),
            max(-90, $this->south - $degrees),
            min(180, $this->east + $degrees),
            min(90, $this->north + $degrees),
        );
    }

    public function contains(float $latitude, float $longitude): bool
    {
        return $latitude >= $this->south && $latitude <= $this->north
            && $longitude >= $this->west && $longitude <= $this->east;
    }

    /**
     * Formatted for the FIRMS Area API.
     */
    public function toFirmsString(): string
    {
        return implode(',', [$this->west, $this->south, $this->east, $this->north]);
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function toArray(): array
    {
        return [$this->west, $this->south, $this->east, $this->north];
    }

    /**
     * @return array{lat: float, lon: float}
     */
    public function center(): array
    {
        return [
            'lat' => ($this->south + $this->north) / 2,
            'lon' => ($this->west + $this->east) / 2,
        ];
    }

    /**
     * Approximate area using a spherical Earth.
     */
    public function areaKm2(): float
    {
        $radius = Grid::EARTH_RADIUS_KM;
        $lonSpan = deg2rad($this->east - $this->west);
        $latBand = sin(deg2rad($this->north)) - sin(deg2rad($this->south));

        return abs($radius ** 2 * $lonSpan * $latBand);
    }

    public function widthDegrees(): float
    {
        return $this->east - $this->west;
    }

    public function heightDegrees(): float
    {
        return $this->north - $this->south;
    }
}
