<?php

namespace App\Http\Controllers;

use App\Services\Insights\ValidationSummary;
use App\Support\Stats\FrpHistogram;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MethodController extends Controller
{
    public function __invoke(Request $request, ValidationSummary $validation): Response
    {
        return Inertia::render('Method', [
            'gridKm' => (float) config('continuum.grid_km'),
            'calendarCellDeg' => (float) config('continuum.calendar_cell_deg'),
            'frpEdges' => FrpHistogram::EDGES,
            'calibration' => config('continuum.calibration'),
            'anomalies' => config('continuum.anomalies'),
            'validation' => $validation->rows($request->user()),
            'thresholds' => ['small_sample_days' => ValidationSummary::SMALL_SAMPLE_DAYS, 'weak_r2' => ValidationSummary::WEAK_R2],
        ]);
    }
}
