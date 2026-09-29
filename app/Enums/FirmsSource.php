<?php

namespace App\Enums;

/**
 * Data sources accepted by the FIRMS Area API.
 */
enum FirmsSource: string
{
    case ModisNrt = 'MODIS_NRT';
    case ModisSp = 'MODIS_SP';
    case ViirsSnppNrt = 'VIIRS_SNPP_NRT';
    case ViirsSnppSp = 'VIIRS_SNPP_SP';
    case ViirsNoaa20Nrt = 'VIIRS_NOAA20_NRT';
    case ViirsNoaa20Sp = 'VIIRS_NOAA20_SP';
    case ViirsNoaa21Nrt = 'VIIRS_NOAA21_NRT';

    public function instrument(): Instrument
    {
        return match ($this) {
            self::ModisNrt, self::ModisSp => Instrument::Modis,
            default => Instrument::Viirs,
        };
    }

    public function isStandardProcessing(): bool
    {
        return str_ends_with($this->value, '_SP');
    }

    /**
     * First date with data for this source.
     */
    public function earliestDate(): string
    {
        return match ($this) {
            self::ModisSp => '2000-11-01',
            self::ViirsSnppSp => '2012-01-20',
            self::ViirsNoaa20Sp => '2018-01-01',
            self::ViirsNoaa21Nrt => '2023-01-17',
            default => now()->subMonths(2)->toDateString(),
        };
    }

    /**
     * Sources to use for a multi-year backfill: standard processing for the
     * archive, NRT for the most recent months.
     *
     * @return list<self>
     */
    public static function archiveSources(): array
    {
        return [self::ModisSp, self::ViirsSnppSp, self::ViirsNoaa20Sp];
    }

    /**
     * Near-real-time sources, which cover the weeks the archive has not
     * reached yet.
     *
     * @return list<self>
     */
    public static function nrtSources(): array
    {
        return [self::ModisNrt, self::ViirsSnppNrt, self::ViirsNoaa20Nrt, self::ViirsNoaa21Nrt];
    }
}
