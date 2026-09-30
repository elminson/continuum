<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use App\Http\Resources\RegionResource;
use App\Models\Alert;
use App\Models\AlertTriage;
use App\Models\Region;
use App\Services\Insights\DataIntake;
use App\Services\Insights\RegionSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RegionSummary $summary, DataIntake $intake): Response
    {
        $regions = Region::query()
            ->visibleTo($request->user())
            ->orderByDesc('is_preset')
            ->orderBy('name')
            ->get()
            ->each(fn (Region $region) => $region->setAttribute('summary', $summary->for($region)));

        $alerts = Alert::query()
            ->with('region')
            ->whereIn('region_id', $regions->pluck('id'))
            ->orderByDesc('z_score')
            ->limit(6)
            ->get();

        AlertTriage::attachTo($alerts);

        return Inertia::render('Dashboard', [
            'global' => $summary->global(),
            'regions' => RegionResource::collection($regions)->resolve(),
            'alerts' => AlertResource::collection($alerts)->resolve(),
            'intake' => Inertia::defer(fn () => $intake->forPresets()),
        ]);
    }
}
