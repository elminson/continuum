<?php

namespace App\Enums;

enum FeedWindow: string
{
    case Day = '24h';
    case TwoDays = '48h';
    case Week = '7d';

    public function days(): int
    {
        return match ($this) {
            self::Day => 1,
            self::TwoDays => 2,
            self::Week => 7,
        };
    }
}
