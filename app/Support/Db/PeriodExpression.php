<?php

namespace App\Support\Db;

use App\Enums\CalendarPeriod;
use InvalidArgumentException;

/**
 * Database-specific SQL that truncates the acq_date column to a calendar period.
 * Weeks start on Monday on every driver.
 */
final class PeriodExpression
{
    /**
     * @return literal-string
     */
    public static function sql(CalendarPeriod $period, string $driver): string
    {
        if ($period === CalendarPeriod::Day) {
            return 'acq_date';
        }

        return match ($driver) {
            'pgsql' => match ($period) {
                CalendarPeriod::Week => "date_trunc('week', acq_date)::date",
                CalendarPeriod::Month => "date_trunc('month', acq_date)::date",
            },
            'sqlite' => match ($period) {
                CalendarPeriod::Week => "date(acq_date, '-' || ((strftime('%w', acq_date) + 6) % 7) || ' days')",
                CalendarPeriod::Month => "date(acq_date, 'start of month')",
            },
            'mysql', 'mariadb' => match ($period) {
                CalendarPeriod::Week => 'DATE_SUB(acq_date, INTERVAL WEEKDAY(acq_date) DAY)',
                CalendarPeriod::Month => "DATE_FORMAT(acq_date, '%Y-%m-01')",
            },
            default => throw new InvalidArgumentException("Unsupported database driver [{$driver}]."),
        };
    }
}
