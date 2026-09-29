<?php

namespace App\Actions\Harmonization;

use App\Enums\CalendarPeriod;
use App\Models\Region;

/**
 * Full harmonization pipeline for one region: native calendars for every
 * period, calibration on the overlap days, then anomaly screening.
 */
class HarmonizeRegion
{
    public function __construct(
        private readonly BuildCalendar $buildCalendar,
        private readonly CalibrateRegion $calibrateRegion,
        private readonly DetectAnomalies $detectAnomalies,
    ) {}

    /**
     * @return array{entries: array<string, int>, calibration: array<int, array<string, mixed>>, alerts: array<string, int>}
     */
    public function __invoke(Region $region): array
    {
        $entries = [];

        foreach (CalendarPeriod::cases() as $period) {
            $entries[$period->value] = ($this->buildCalendar)($region, $period);
        }

        $calibration = ($this->calibrateRegion)($region);

        $alerts = [
            CalendarPeriod::Week->value => ($this->detectAnomalies)($region, CalendarPeriod::Week),
            CalendarPeriod::Month->value => ($this->detectAnomalies)($region, CalendarPeriod::Month),
        ];

        $region->forceFill(['harmonized_at' => now()])->save();

        return compact('entries', 'calibration', 'alerts');
    }
}
