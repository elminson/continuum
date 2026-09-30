<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        /** @var list<int> $regionIds */
        $regionIds = Region::query()->visibleTo($request->user())->pluck('id')->values()->all();

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
            'map' => $this->mapRegions($regionIds, $validated['severity'] ?? null),
        ]);
    }

    /**
     * Every visible area with its flagged-period counts and worst alert, for
     * the map. The severity filter applies; the region filter does not, so the
     * map keeps showing where else something is going on.
     *
     * @param  list<int>  $regionIds
     * @return list<array<string, mixed>>
     */
    private function mapRegions(array $regionIds, ?string $severity): array
    {
        $counts = DB::table('alerts')
            ->whereIn('region_id', $regionIds)
            ->when($severity, fn ($query, $value) => $query->where('severity', $value))
            ->selectRaw('region_id, severity, count(*) as total')
            ->groupBy('region_id', 'severity')
            ->get()
            ->groupBy('region_id');

        $top = Alert::query()
            ->whereIn('region_id', $regionIds)
            ->when($severity, fn ($query, $value) => $query->where('severity', $value))
            ->selectRaw('distinct on (region_id) *')
            ->orderBy('region_id')
            ->orderByDesc('z_score')
            ->get()
            ->keyBy('region_id');

        $regions = Region::query()
            ->whereIn('id', $regionIds)
            ->orderByDesc('is_preset')
            ->orderBy('name')
            ->get()
            ->map(function (Region $region) use ($counts, $top): array {
                $bySeverity = $counts->get($region->id, collect())->pluck('total', 'severity');
                $best = $top->get($region->id);

                return [
                    'slug' => $region->slug,
                    'name' => $region->name,
                    'color' => $region->color,
                    'is_preset' => $region->is_preset,
                    'bbox' => $region->bbox()->toArray(),
                    'center' => $region->bbox()->center(),
                    'alerts' => [
                        'total' => (int) $bySeverity->sum(),
                        'critical' => (int) $bySeverity->get(AlertSeverity::Critical->value, 0),
                        'warning' => (int) $bySeverity->get(AlertSeverity::Warning->value, 0),
                        'watch' => (int) $bySeverity->get(AlertSeverity::Watch->value, 0),
                    ],
                    'top' => $best === null ? null : [
                        'period' => $best->period->value,
                        'period_start' => $best->period_start->toDateString(),
                        'severity' => $best->severity->value,
                        'z_score' => (float) $best->z_score,
                        'message' => $best->message,
                    ],
                ];
            })
            ->all();

        return array_values($regions);
    }
}
