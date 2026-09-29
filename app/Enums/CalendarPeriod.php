<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum CalendarPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function startOf(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek(CarbonImmutable::MONDAY),
            self::Month => $date->startOfMonth(),
        };
    }

    public function next(CarbonImmutable $periodStart): CarbonImmutable
    {
        return match ($this) {
            self::Day => $periodStart->addDay(),
            self::Week => $periodStart->addWeek(),
            self::Month => $periodStart->addMonth(),
        };
    }

    /**
     * Position of the period within the year, used to build climatologies.
     */
    public function seasonalIndex(CarbonImmutable $periodStart): int
    {
        return match ($this) {
            self::Day => $periodStart->dayOfYear,
            self::Week => min(52, $periodStart->isoWeek),
            self::Month => $periodStart->month,
        };
    }

    public function sqlTruncUnit(): string
    {
        return $this->value;
    }
}
