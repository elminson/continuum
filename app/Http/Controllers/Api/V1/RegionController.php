<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\Insights\RegionSummary;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RegionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $regions = Region::query()->preset()->orderBy('name')->get();

        return RegionResource::collection($regions);
    }

    public function show(Region $region, RegionSummary $summary): RegionResource
    {
        Gate::authorize('view', $region);

        $region->setAttribute('summary', $summary->for($region));

        return RegionResource::make($region);
    }
}
