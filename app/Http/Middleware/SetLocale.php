<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Speaks the signed-in user's chosen language, or the browser's for guests.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()->locale
            ?? $request->getPreferredLanguage(Locale::values())
            ?? config('app.locale');

        app()->setLocale($locale);
        CarbonImmutable::setLocale($locale);

        return $next($request);
    }
}
