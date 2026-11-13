<?php

namespace App\Http\Controllers;

use App\Enums\IngestStatus;
use App\Models\IngestRun;
use App\Models\Region;
use App\Services\Firms\BackfillPlanner;
use App\Services\Insights\DataIntake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IngestionController extends Controller
{
    public function __invoke(Request $request, DataIntake $intake, BackfillPlanner $planner): Response
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::enum(IngestStatus::class)],
            'area' => ['sometimes', 'nullable', 'string'],
        ]);

        $regions = Region::query()->visibleTo($request->user())->orderByDesc('is_preset')->orderBy('name')->get();
        $regionIds = $regions->pluck('id')->all();

        // Runs of areas the viewer can see, plus the shared global feeds.
        $visible = fn (Builder $query) => $query->whereIn('region_id', $regionIds)->orWhere('source', 'like', 'public:%');

        $runs = IngestRun::query()
            ->with('region:id,slug,name,color')
            ->where($visible)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['area'] ?? null, function ($query, $slug) {
                $slug === 'public-feeds'
                    ? $query->where('source', 'like', 'public:%')
                    : $query->whereRelation('region', 'slug', $slug);
            })
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (IngestRun $run): array => [
                'id' => $run->id,
                'source' => $run->source,
                'label' => $intake->sourceLabel($run->source),
                'instrument' => $run->instrument?->value,
                'region' => $run->region?->only(['slug', 'name', 'color']),
                'status' => $run->status->value,
                'rows_read' => $run->rows_read,
                'rows_inserted' => $run->rows_inserted,
                'rows_skipped' => $run->rows_skipped,
                'date_from' => $run->date_from?->toDateString(),
                'date_to' => $run->date_to?->toDateString(),
                'started_at' => $run->started_at->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'duration_s' => $run->finished_at === null ? null : max(0, (int) $run->started_at->diffInSeconds($run->finished_at)),
                'error' => $run->error,
            ]);

        $counts = DB::table('ingest_runs')
            ->where(fn ($query) => $query->whereIn('region_id', $regionIds)->orWhere('source', 'like', 'public:%'))
            ->selectRaw('status, count(*) as total, coalesce(sum(rows_inserted), 0) as rows')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $active = $planner->activeBatches($regions);

        $batches = $regions
            ->map(fn (Region $region) => ($batch = $active[$region->id] ?? null) === null ? null : [
                ...$batch,
                'percent' => $batch['total'] > 0 ? round(100 * $batch['done'] / $batch['total'], 1) : 0.0,
                'region' => $region->only(['slug', 'name', 'color']),
            ])
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Ingestion', [
            'runs' => $runs,
            'filters' => ['status' => $validated['status'] ?? null, 'area' => $validated['area'] ?? null],
            'areas' => $regions->map(fn (Region $region) => $region->only(['slug', 'name', 'color']))->values()->all(),
            'summary' => [
                'completed' => (int) ($counts->get('completed')->total ?? 0),
                'running' => (int) ($counts->get('running')->total ?? 0),
                'failed' => (int) ($counts->get('failed')->total ?? 0),
                'rows_inserted' => (int) $counts->sum('rows'),
            ],
            'batches' => $batches,
        ]);
    }
}
