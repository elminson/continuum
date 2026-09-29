<?php

namespace App\Enums;

/**
 * Key-free FIRMS "Active Fire Data" text feeds.
 *
 * @see https://firms.modaps.eosdis.nasa.gov/active_fire/
 */
enum PublicFeed: string
{
    case Modis = 'modis';
    case ViirsSnpp = 'viirs-snpp';
    case ViirsNoaa20 = 'viirs-noaa20';
    case ViirsNoaa21 = 'viirs-noaa21';

    public function instrument(): Instrument
    {
        return $this === self::Modis ? Instrument::Modis : Instrument::Viirs;
    }

    public function label(): string
    {
        return match ($this) {
            self::Modis => 'MODIS C6.1 (Terra + Aqua)',
            self::ViirsSnpp => 'VIIRS C2 Suomi NPP',
            self::ViirsNoaa20 => 'VIIRS C2 NOAA-20',
            self::ViirsNoaa21 => 'VIIRS C2 NOAA-21',
        };
    }

    public function path(FeedWindow $window): string
    {
        $suffix = $window->value;

        return match ($this) {
            self::Modis => "data/active_fire/modis-c6.1/csv/MODIS_C6_1_Global_{$suffix}.csv",
            self::ViirsSnpp => "data/active_fire/suomi-npp-viirs-c2/csv/SUOMI_VIIRS_C2_Global_{$suffix}.csv",
            self::ViirsNoaa20 => "data/active_fire/noaa-20-viirs-c2/csv/J1_VIIRS_C2_Global_{$suffix}.csv",
            self::ViirsNoaa21 => "data/active_fire/noaa-21-viirs-c2/csv/J2_VIIRS_C2_Global_{$suffix}.csv",
        };
    }
}
