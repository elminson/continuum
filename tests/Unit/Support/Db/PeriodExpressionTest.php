<?php

use App\Enums\CalendarPeriod;
use App\Support\Db\PeriodExpression;

it('returns the raw column for daily periods on every driver', function (string $driver) {
    expect(PeriodExpression::sql(CalendarPeriod::Day, $driver))->toBe('acq_date');
})->with(['pgsql', 'sqlite', 'mysql']);

it('truncates to Monday-based weeks and to months per driver', function (string $driver, string $week, string $month) {
    expect(PeriodExpression::sql(CalendarPeriod::Week, $driver))->toBe($week)
        ->and(PeriodExpression::sql(CalendarPeriod::Month, $driver))->toBe($month);
})->with([
    'pgsql' => ['pgsql', "date_trunc('week', acq_date)::date", "date_trunc('month', acq_date)::date"],
    'sqlite' => ['sqlite', "date(acq_date, '-' || ((strftime('%w', acq_date) + 6) % 7) || ' days')", "date(acq_date, 'start of month')"],
    'mysql' => ['mysql', 'DATE_SUB(acq_date, INTERVAL WEEKDAY(acq_date) DAY)', "DATE_FORMAT(acq_date, '%Y-%m-01')"],
]);

it('rejects unknown drivers', function () {
    PeriodExpression::sql(CalendarPeriod::Month, 'oracle');
})->throws(InvalidArgumentException::class);
