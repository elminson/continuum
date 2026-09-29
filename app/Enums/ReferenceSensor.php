<?php

namespace App\Enums;

/**
 * Which sensor scale a harmonized series is expressed in.
 */
enum ReferenceSensor: string
{
    case Modis = 'modis';
    case Viirs = 'viirs';

    public function instrument(): Instrument
    {
        return Instrument::from($this->value);
    }

    public function other(): Instrument
    {
        return $this === self::Modis ? Instrument::Viirs : Instrument::Modis;
    }

    public function label(): string
    {
        return match ($this) {
            self::Modis => 'MODIS-equivalent (26-year baseline)',
            self::Viirs => 'VIIRS-equivalent (modern sensitivity)',
        };
    }
}
