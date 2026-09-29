<?php

namespace App\Enums;

use InvalidArgumentException;

enum Satellite: string
{
    case Terra = 'T';
    case Aqua = 'A';
    case SuomiNpp = 'N';
    case Noaa20 = 'N20';
    case Noaa21 = 'N21';

    public function label(): string
    {
        return match ($this) {
            self::Terra => 'Terra',
            self::Aqua => 'Aqua',
            self::SuomiNpp => 'Suomi NPP',
            self::Noaa20 => 'NOAA-20',
            self::Noaa21 => 'NOAA-21',
        };
    }

    public function instrument(): Instrument
    {
        return match ($this) {
            self::Terra, self::Aqua => Instrument::Modis,
            self::SuomiNpp, self::Noaa20, self::Noaa21 => Instrument::Viirs,
        };
    }

    /**
     * Local solar time of the daytime equator crossing. Aqua and the three
     * VIIRS platforms share the 13:30 "afternoon train" orbit, which makes
     * them the physically comparable pair; Terra observes at 10:30.
     */
    public function equatorCrossingLocalTime(): string
    {
        return match ($this) {
            self::Terra => '10:30',
            default => '13:30',
        };
    }

    public function isAfternoonOrbit(): bool
    {
        return $this !== self::Terra;
    }

    public function launchYear(): int
    {
        return match ($this) {
            self::Terra => 1999,
            self::Aqua => 2002,
            self::SuomiNpp => 2011,
            self::Noaa20 => 2017,
            self::Noaa21 => 2022,
        };
    }

    /**
     * Accept every spelling used across FIRMS feeds and archive downloads.
     */
    public static function fromFirms(string $code): self
    {
        $normalized = strtoupper(trim($code));

        return match ($normalized) {
            'T', 'TERRA' => self::Terra,
            'A', 'AQUA' => self::Aqua,
            'N', 'NPP', 'SNPP', 'SUOMI NPP', 'SUOMI-NPP' => self::SuomiNpp,
            'N20', 'NOAA-20', 'NOAA20', '1' => self::Noaa20,
            'N21', 'NOAA-21', 'NOAA21', '2' => self::Noaa21,
            default => throw new InvalidArgumentException("Unknown FIRMS satellite code [{$code}]."),
        };
    }

    /**
     * @return list<self>
     */
    public static function forInstrument(Instrument $instrument): array
    {
        return array_values(array_filter(self::cases(), fn (self $satellite) => $satellite->instrument() === $instrument));
    }
}
