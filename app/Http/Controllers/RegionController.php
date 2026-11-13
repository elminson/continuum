<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Jobs\HarmonizeRegionJob;
use App\Models\Region;
use App\Services\Firms\AreaApiClient;
use App\Services\Firms\BackfillPlanner;
use App\Services\Insights\RegionSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegionController extends Controller
{
    public function index(Request $request, RegionSummary $summary): Response
    {
        $regions = Region::query()
            ->visibleTo($request->user())
            ->orderByDesc('is_preset')
            ->orderBy('name')
            ->get()
            ->each(fn (Region $region) => $region->setAttribute('summary', $summary->for($region)));

        return Inertia::render('regions/Index', [
            'regions' => RegionResource::collection($regions)->resolve(),
            'canCreate' => $request->user() !== null,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Region::class);

        return Inertia::render('regions/Form', [
            'region' => null,
        ]);
    }

    public function store(StoreRegionRequest $request): RedirectResponse
    {
        $region = Region::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'is_preset' => false,
        ]);

        return redirect()
            ->route('regions.show', $region)
            ->with('success', __('Area of interest created.').' '.$this->startBackfill($region));
    }

    public function backfill(Request $request, Region $region): RedirectResponse
    {
        Gate::authorize('backfill', $region);

        return back()->with('success', $this->startBackfill($region));
    }

    /**
     * Queue the full FIRMS history when a key is configured; otherwise just
     * harmonize whatever detections already exist inside the box.
     */
    private function startBackfill(Region $region): string
    {
        $planner = app(BackfillPlanner::class);

        if (! app(AreaApiClient::class)->hasKey()) {
            HarmonizeRegionJob::dispatch($region->id);

            return __('Harmonizing existing detections. Set FIRMS_MAP_KEY to fetch the full history automatically.');
        }

        if (($active = $planner->activeBatch($region)) !== null) {
            return __('A backfill is already running for this area (:done of :total windows done).', ['done' => $active['done'], 'total' => $active['total']]);
        }

        $batch = $planner->dispatch($region, $planner->fullHistory($region));

        return __('Fetching the full FIRMS history: :count windows queued, newest first. Harmonization follows automatically.', ['count' => $batch->totalJobs]);
    }

    public function edit(Region $region): Response
    {
        Gate::authorize('update', $region);

        return Inertia::render('regions/Form', [
            'region' => RegionResource::make($region)->resolve(),
        ]);
    }

    public function update(UpdateRegionRequest $request, Region $region): RedirectResponse
    {
        $boxChanged = collect(['west', 'south', 'east', 'north'])
            ->contains(fn (string $key) => (float) $request->validated($key) !== (float) $region->{$key});

        $region->update($request->validated());

        if ($boxChanged) {
            HarmonizeRegionJob::dispatch($region->id);
        }

        return redirect()
            ->route('regions.show', $region)
            ->with('success', $boxChanged ? __('Area updated. Re-harmonizing with the new extent.') : __('Area updated.'));
    }

    public function destroy(Request $request, Region $region): RedirectResponse
    {
        Gate::authorize('delete', $region);

        $region->delete();

        return redirect()->route('regions.index')->with('success', __('Area of interest removed.'));
    }

    public function harmonize(Request $request, Region $region): RedirectResponse
    {
        Gate::authorize('view', $region);

        HarmonizeRegionJob::dispatch($region->id);

        return back()->with('success', __('Harmonization queued for :name.', ['name' => $region->name]));
    }
}
