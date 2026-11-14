<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Services\Insights\ModisRetirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ModisRetirementController extends Controller
{
    public function __invoke(Request $request, Region $region, ModisRetirement $retirement): JsonResponse
    {
        Gate::authorize('view', $region);

        return response()->json(['data' => $retirement->simulate($region, $request->integer('year') ?: null)]);
    }
}
