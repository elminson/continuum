<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalibrationFactorResource;
use App\Models\Region;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CalibrationController extends Controller
{
    public function __invoke(Region $region): AnonymousResourceCollection
    {
        Gate::authorize('view', $region);

        return CalibrationFactorResource::collection(
            $region->calibrationFactors()->orderBy('method')->orderBy('reference')->orderBy('month')->get(),
        );
    }
}
