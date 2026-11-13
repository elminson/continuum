<?php

namespace App\Support;

use App\Enums\CalendarPeriod;
use App\Enums\ReferenceSensor;
use Carbon\CarbonImmutable;

/**
 * The one-line summary of an unusual period, in any language the app speaks:
 * "Week of 7 Sep 2026: 1,234 MODIS-equivalent cell-days, 3.2x the median of 14
 * other years (z = 4.1)."
 */
final class AlertMessage
{
    public static function describe(
        CalendarPeriod $period,
        CarbonImmutable $start,
        float $observed,
        float $expected,
        float $z,
        int $years,
        ReferenceSensor $reference,
        ?string $locale = null,
    ): string {
        $locale ??= app()->getLocale();
        /** @var CarbonImmutable $date */
        $date = $start->locale($locale);

        $label = match ($period) {
            CalendarPeriod::Day => $date->translatedFormat('j M Y'),
            CalendarPeriod::Week => __('Week of :date', ['date' => $date->translatedFormat('j M Y')], $locale),
            CalendarPeriod::Month => $date->translatedFormat('F Y'),
        };

        $multiple = $expected > 0 ? round($observed / $expected, 1) : null;

        return __(':label: :observed :sensor-equivalent cell-days, :multiple the median of :years other years (z = :z).', [
            'label' => $label,
            'observed' => self::number($observed, 0, $locale),
            'sensor' => $reference === ReferenceSensor::Modis ? 'MODIS' : 'VIIRS',
            'multiple' => $multiple === null ? __('far above', locale: $locale) : self::number($multiple, null, $locale).'x',
            'years' => $years,
            'z' => self::number($z, 1, $locale),
        ], $locale);
    }

    /**
     * English groups with "," and Spanish with "."; null decimals keeps the
     * value's own (3.0 prints as 3, like PHP's string cast).
     */
    private static function number(float $value, ?int $decimals, string $locale): string
    {
        [$point, $thousands] = $locale === 'es' ? [',', '.'] : ['.', ','];

        if ($decimals === null) {
            return str_replace('.', $point, (string) $value);
        }

        return number_format($value, $decimals, $point, $thousands);
    }
}
