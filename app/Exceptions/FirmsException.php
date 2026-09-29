<?php

namespace App\Exceptions;

use RuntimeException;

class FirmsException extends RuntimeException
{
    public static function missingMapKey(): self
    {
        return new self('No FIRMS MAP_KEY configured. Request a free key at https://firms.modaps.eosdis.nasa.gov/api/map_key/ and set FIRMS_MAP_KEY in .env.');
    }

    public static function invalidMapKey(): self
    {
        return new self('FIRMS rejected the MAP_KEY. Check FIRMS_MAP_KEY in .env.');
    }

    public static function unexpectedResponse(string $snippet): self
    {
        return new self('FIRMS returned an unexpected response: '.mb_substr(trim($snippet), 0, 200));
    }

    public static function rateLimited(): self
    {
        $exception = new self('FIRMS transaction budget for the current 10-minute window is exhausted; requests are paused until it resets.');
        $exception->rateLimited = true;

        return $exception;
    }

    public bool $rateLimited = false;
}
