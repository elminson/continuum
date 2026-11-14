<?php

namespace App\Http\Controllers;

use App\Http\Resources\CalibrationFactorResource;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\Insights\ModisRetirement;
use App\Services\Insights\OverlapAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LabController extends Controller
{
    public function __invoke(Request $request, Region $region, OverlapAnalysis $analysis, ModisRetirement $retirement): Response
    {
        Gate::authorize('view', $region);

        return Inertia::render('regions/Lab', [
            'region' => RegionResource::make($region)->resolve(),
            'pairs' => $analysis->pairs($region),
            'factors' => CalibrationFactorResource::collection($region->calibrationFactors()->orderBy('method')->orderBy('reference')->orderBy('month')->get())->resolve(),
            'thresholdCurve' => $analysis->thresholdCurve($region),
            'detectionCurve' => Inertia::defer(fn () => $analysis->detectionCurve($region)),
            'retirement' => Inertia::defer(fn () => $retirement->simulate($region, $request->integer('retire') ?: null), 'retirement'),
        ]);
    }
}
