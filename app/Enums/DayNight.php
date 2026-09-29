<?php

namespace App\Enums;

enum DayNight: string
{
    case Day = 'D';
    case Night = 'N';

    public static function fromFirms(string $value): self
    {
        return strtoupper(trim($value)) === 'N' ? self::Night : self::Day;
    }
}
