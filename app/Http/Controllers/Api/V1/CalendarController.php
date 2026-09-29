<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarQueryRequest;
use App\Models\Region;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Services\Harmonization\MethodReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CalendarController extends Controller
{
    public function __invoke(CalendarQueryRequest $request, Region $region, HarmonizedCalendar $calendar, MethodReceipt $receipt): JsonResponse
    {
        Gate::authorize('view', $region);

        $series = $calendar->series($region, $request->period(), $request->harmonizationMethod(), $request->reference(), $request->from(), $request->to());

        return response()->json([
            'data' => $series['points'],
            'meta' => [
                'region' => $region->slug,
                'period' => $series['period'],
                'method' => $series['method'],
                'reference' => $series['reference'],
                'calibrated' => $series['calibrated'],
                'factors' => $series['factors'],
                'validation' => $series['validation'],
                'count' => count($series['points']),
                'receipt' => $receipt->for($region, $request->period(), $request->harmonizationMethod(), $request->reference()),
            ],
        ]);
    }
}
