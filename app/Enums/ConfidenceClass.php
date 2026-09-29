<?php

namespace App\Enums;

/**
 * Common confidence scale. MODIS reports 0-100; VIIRS reports low/nominal/high.
 * FIRMS maps MODIS 0-29 to low, 30-79 to nominal and 80-100 to high.
 */
enum ConfidenceClass: string
{
    case Low = 'low';
    case Nominal = 'nominal';
    case High = 'high';

    public static function fromModis(int $confidence): self
    {
        return match (true) {
            $confidence < 30 => self::Low,
            $confidence < 80 => self::Nominal,
            default => self::High,
        };
    }

    public static function fromViirs(string $confidence): self
    {
        return match (strtolower(trim($confidence))) {
            'l', 'low' => self::Low,
            'h', 'high' => self::High,
            default => self::Nominal,
        };
    }

    public static function fromRaw(Instrument $instrument, string $raw): self
    {
        return $instrument === Instrument::Modis
            ? self::fromModis((int) $raw)
            : self::fromViirs($raw);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Nominal => 1,
            self::High => 2,
        };
    }
}
