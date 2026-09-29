<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DetectionQueryRequest;
use App\Models\CellDay;
use App\Models\Detection;
use App\Support\Geo\Grid;
use Illuminate\Http\JsonResponse;

/**
 * GeoJSON of what burned inside a box on one day: grid cell-days by
 * default (the harmonized unit) or raw pixels with raw=1.
 */
class DetectionController extends Controller
{
    public function __invoke(DetectionQueryRequest $request): JsonResponse
    {
        $box = $request->box();
        $instrument = $request->instrument();
        $grid = Grid::fromConfig();

        if ($request->raw()) {
            $features = Detection::query()
                ->withinBox($box)
                ->onDate($request->validated('date'))
                ->when($instrument, fn ($query) => $query->forInstrument($instrument))
                ->orderByDesc('frp')
                ->limit($request->limit())
                ->get()
                ->map(fn (Detection $detection) => [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [$detection->longitude, $detection->latitude]],
                    'properties' => [
                        'instrument' => $detection->instrument->value,
                        'satellite' => $detection->satellite->label(),
                        'acquired_at' => $detection->acquired_at->toIso8601String(),
                        'frp' => $detection->frp,
                        'brightness' => $detection->brightness,
                        'confidence' => $detection->confidence->value,
                        'daynight' => $detection->daynight->value,
                        'scan' => $detection->scan,
                        'track' => $detection->track,
                        'cell_id' => $detection->cell_id,
                    ],
                ]);
        } else {
            $features = CellDay::query()
                ->withinBox($box)
                ->where('acq_date', $request->validated('date'))
                ->when($instrument, fn ($query) => $query->forInstrument($instrument))
                ->orderByDesc('frp_sum')
                ->limit($request->limit())
                ->get()
                ->map(function (CellDay $cell) use ($grid) {
                    $bounds = $grid->cellBounds($cell->cell_id);

                    return [
                        'type' => 'Feature',
                        'geometry' => ['type' => 'Point', 'coordinates' => [$cell->longitude, $cell->latitude]],
                        'properties' => [
                            'cell_id' => $cell->cell_id,
                            'instrument' => $cell->instrument->value,
                            'satellite' => $cell->satellite->label(),
                            'detections' => $cell->detections,
                            'frp_sum' => $cell->frp_sum,
                            'frp_max' => $cell->frp_max,
                            'footprint_km2' => $cell->footprint_km2,
                            'day' => $cell->day_detections,
                            'night' => $cell->night_detections,
                            'high_confidence' => $cell->high_confidence,
                            'bounds' => [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']],
                        ],
                    ];
                });
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features->values(),
            'meta' => [
                'date' => $request->validated('date'),
                'bbox' => $box->toArray(),
                'instrument' => $instrument?->value,
                'raw' => $request->raw(),
                'count' => $features->count(),
                'limit' => $request->limit(),
                'grid_km' => $grid->cellKm,
            ],
        ]);
    }
}
