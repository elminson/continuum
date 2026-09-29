<?php

use App\Enums\CalendarPeriod;
use Carbon\CarbonImmutable;

it('starts weeks on Monday', function () {
    $sunday = CarbonImmutable::parse('2026-09-27');

    expect(CalendarPeriod::Week->startOf($sunday)->toDateString())->toBe('2026-09-21')
        ->and(CalendarPeriod::Week->startOf(CarbonImmutable::parse('2026-09-21'))->toDateString())->toBe('2026-09-21');
});

it('starts months on the first day and days at midnight', function () {
    $date = CarbonImmutable::parse('2026-09-27 15:45');

    expect(CalendarPeriod::Month->startOf($date)->toDateString())->toBe('2026-09-01')
        ->and(CalendarPeriod::Day->startOf($date)->format('Y-m-d H:i'))->toBe('2026-09-27 00:00');
});

it('advances to the next period', function () {
    $start = CarbonImmutable::parse('2026-01-26');

    expect(CalendarPeriod::Week->next($start)->toDateString())->toBe('2026-02-02')
        ->and(CalendarPeriod::Month->next(CarbonImmutable::parse('2026-01-01'))->toDateString())->toBe('2026-02-01')
        ->and(CalendarPeriod::Day->next($start)->toDateString())->toBe('2026-01-27');
});

it('caps the seasonal index of week 53 at 52', function () {
    expect(CalendarPeriod::Week->seasonalIndex(CarbonImmutable::parse('2020-12-28')))->toBe(52)
        ->and(CalendarPeriod::Month->seasonalIndex(CarbonImmutable::parse('2020-08-15')))->toBe(8)
        ->and(CalendarPeriod::Day->seasonalIndex(CarbonImmutable::parse('2021-02-01')))->toBe(32);
});
