<?php

namespace App\Enums;

enum AlertSeverity: string
{
    case Watch = 'watch';
    case Warning = 'warning';
    case Critical = 'critical';

    public static function fromZScore(float $z): ?self
    {
        $thresholds = config('continuum.anomalies.thresholds');

        return match (true) {
            $z >= $thresholds['critical'] => self::Critical,
            $z >= $thresholds['warning'] => self::Warning,
            $z >= $thresholds['watch'] => self::Watch,
            default => null,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Watch => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }
}
