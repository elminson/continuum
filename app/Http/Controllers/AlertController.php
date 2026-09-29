<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'severity' => ['sometimes', 'nullable', Rule::enum(AlertSeverity::class)],
            'region' => ['sometimes', 'nullable', 'string'],
        ]);

        $regionIds = Region::query()->visibleTo($request->user())->pluck('id');

        $alerts = Alert::query()
            ->with('region')
            ->whereIn('region_id', $regionIds)
            ->when($validated['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($validated['region'] ?? null, fn ($query, $slug) => $query->whereRelation('region', 'slug', $slug))
            ->orderByDesc('period_start')
            ->orderByDesc('z_score')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Alerts', [
            'alerts' => AlertResource::collection($alerts),
            'filters' => ['severity' => $validated['severity'] ?? null, 'region' => $validated['region'] ?? null],
            'regions' => Region::query()->whereIn('id', $regionIds)->orderBy('name')->get(['slug', 'name', 'color']),
        ]);
    }
}
