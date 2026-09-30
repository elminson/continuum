<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CalendarPeriod;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\AlertTriage;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    public function __invoke(Request $request, Region $region): AnonymousResourceCollection
    {
        Gate::authorize('view', $region);

        $validated = $request->validate([
            'period' => ['sometimes', Rule::enum(CalendarPeriod::class)],
        ]);

        $alerts = $region->alerts()
            ->when($validated['period'] ?? null, fn ($query, $period) => $query->where('period', $period))
            ->orderByDesc('period_start')
            ->orderByDesc('z_score')
            ->paginate(50);

        AlertTriage::attachTo($alerts->getCollection());

        return AlertResource::collection($alerts);
    }
}
