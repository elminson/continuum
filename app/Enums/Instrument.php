<?php

namespace App\Enums;

enum Instrument: string
{
    case Modis = 'modis';
    case Viirs = 'viirs';

    public function label(): string
    {
        return match ($this) {
            self::Modis => 'MODIS',
            self::Viirs => 'VIIRS',
        };
    }

    /**
     * Nominal nadir pixel size in kilometres.
     */
    public function nominalPixelKm(): float
    {
        return match ($this) {
            self::Modis => 1.0,
            self::Viirs => 0.375,
        };
    }

    /**
     * Nominal nadir pixel area in square kilometres.
     */
    public function nominalPixelAreaKm2(): float
    {
        return $this->nominalPixelKm() ** 2;
    }

    public function recordStart(): string
    {
        return match ($this) {
            self::Modis => config('continuum.record.modis_start'),
            self::Viirs => config('continuum.record.viirs_start'),
        };
    }

    /**
     * Column names used by FIRMS CSV files for this instrument.
     *
     * @return array{brightness: string, secondary: string}
     */
    public function csvColumns(): array
    {
        return match ($this) {
            self::Modis => ['brightness' => 'brightness', 'secondary' => 'bright_t31'],
            self::Viirs => ['brightness' => 'bright_ti4', 'secondary' => 'bright_ti5'],
        };
    }

    /**
     * @param  list<string>  $header
     */
    public static function fromHeader(array $header): self
    {
        return in_array('bright_ti4', $header, true) ? self::Viirs : self::Modis;
    }
}
