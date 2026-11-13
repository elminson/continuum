<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Spanish = 'es';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Spanish => 'Español',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
