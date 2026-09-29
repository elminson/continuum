<?php

namespace App\Enums;

enum HarmonizationMethod: string
{
    /**
     * Per-region, per-month ratio of MODIS to VIIRS active cell-days fitted
     * on the sensor-overlap period, with a bootstrap confidence interval.
     */
    case Ratio = 'ratio';

    /**
     * "MODIS-visibility" filter: a VIIRS cell-day counts as MODIS-equivalent
     * only if its summed FRP reaches a threshold tau calibrated so that the
     * filtered VIIRS series matches MODIS on the overlap period.
     */
    case FrpThreshold = 'frp_threshold';

    public function label(): string
    {
        return match ($this) {
            self::Ratio => 'Monthly ratio (bootstrap CI)',
            self::FrpThreshold => 'FRP visibility threshold',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ratio => 'Scales one sensor to the other with a per-month ratio of active cell-days measured while both sensors flew.',
            self::FrpThreshold => 'Keeps only the VIIRS cell-days whose summed fire radiative power a 1 km MODIS pixel could have detected.',
        };
    }
}
