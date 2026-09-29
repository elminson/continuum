<?php

namespace App\Http\Controllers;

use App\Support\Stats\FrpHistogram;
use Inertia\Inertia;
use Inertia\Response;

class MethodController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Method', [
            'gridKm' => (float) config('continuum.grid_km'),
            'calendarCellDeg' => (float) config('continuum.calendar_cell_deg'),
            'frpEdges' => FrpHistogram::EDGES,
            'calibration' => config('continuum.calibration'),
            'anomalies' => config('continuum.anomalies'),
        ]);
    }
}
