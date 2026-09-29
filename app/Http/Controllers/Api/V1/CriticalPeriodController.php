<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarQueryRequest;
use App\Models\Region;
use App\Services\Harmonization\CriticalPeriods;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CriticalPeriodController extends Controller
{
    public function __invoke(CalendarQueryRequest $request, Region $region, CriticalPeriods $critical): JsonResponse
    {
        Gate::authorize('view', $region);

        return response()->json([
            'data' => $critical->forRegion($region, $request->period(), $request->harmonizationMethod(), $request->reference()),
        ]);
    }
}
