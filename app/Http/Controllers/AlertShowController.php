<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use App\Http\Resources\RegionResource;
use App\Models\Alert;
use App\Models\AlertTriage;
use App\Services\Insights\AlertDetail;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AlertShowController extends Controller
{
    public function __invoke(Alert $alert, AlertDetail $detail): Response
    {
        $alert->load('region');
        Gate::authorize('view', $alert->region);
        AlertTriage::attachTo([$alert]);

        return Inertia::render('alerts/Show', [
            'alert' => AlertResource::make($alert)->resolve(),
            'region' => RegionResource::make($alert->region)->resolve(),
            'detail' => Inertia::defer(fn () => $detail->for($alert)),
        ]);
    }
}
