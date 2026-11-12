<?php

namespace App\Http\Controllers;

use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Http\Requests\CalendarQueryRequest;
use App\Http\Resources\AlertResource;
use App\Http\Resources\CalibrationFactorResource;
use App\Http\Resources\RegionResource;
use App\Models\AlertTriage;
use App\Models\Region;
use App\Services\Firms\AreaApiClient;
use App\Services\Firms\BackfillPlanner;
use App\Services\Harmonization\CriticalPeriods;
use App\Services\Harmonization\HarmonizedCalendar;
use App\Services\Harmonization\MethodReceipt;
use App\Services\Insights\PeriodComparison;
use App\Services\Insights\RegionSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(
        CalendarQueryRequest $request,
        Region $region,
        HarmonizedCalendar $calendar,
        CriticalPeriods $critical,
        MethodReceipt $receipt,
        RegionSummary $summary,
        BackfillPlanner $planner,
        AreaApiClient $firms,
        PeriodComparison $comparison,
    ): Response {
        Gate::authorize('view', $region);

        $region->setAttribute('summary', $regionSummary = $summary->for($region));

        $recordDays = $regionSummary['first_date'] !== null && $regionSummary['last_date'] !== null
            ? (int) CarbonImmutable::parse($regionSummary['first_date'])->diffInDays(CarbonImmutable::parse($regionSummary['last_date'])) + 1
            : null;

        $from = $request->from();
        $to = $request->to();
        $rangeDays = $from !== null && $to !== null
            ? (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1
            : null;

        $period = $request->period($rangeDays ?? $recordDays);
        $method = $request->harmonizationMethod();
        $reference = $request->reference();

        return Inertia::render('regions/Show', [
            'region' => RegionResource::make($region)->resolve(),
            'filters' => [
                'period' => $period->value,
                'method' => $method->value,
                'reference' => $reference->value,
                'from' => $from,
                'to' => $to,
            ],
            'options' => [
                'periods' => array_map(fn (CalendarPeriod $p) => ['value' => $p->value, 'label' => ucfirst($p->value)], CalendarPeriod::cases()),
                'methods' => array_map(fn (HarmonizationMethod $m) => ['value' => $m->value, 'label' => $m->label(), 'description' => $m->description()], HarmonizationMethod::cases()),
                'references' => array_map(fn (ReferenceSensor $r) => ['value' => $r->value, 'label' => $r->label()], ReferenceSensor::cases()),
            ],
            'series' => $calendar->series($region, $period, $method, $reference, $from, $to),
            'comparison' => function () use ($comparison, $region, $regionSummary, $from, $to, $method, $reference): ?array {
                [$windowFrom, $windowTo] = $from !== null && $to !== null
                    ? [$from, $to]
                    : ($comparison->defaultWindow($regionSummary['last_date']) ?? [null, null]);

                return $windowFrom === null || $windowTo === null
                    ? null
                    : $comparison->forRegion($region, $windowFrom, $windowTo, $method, $reference);
            },
            'critical' => $critical->forRegion($region, CalendarPeriod::Month, $method, $reference),
            'alerts' => AlertResource::collection(tap($region->alerts()->orderByDesc('z_score')->limit(12)->get(), AlertTriage::attachTo(...)))->resolve(),
            'factors' => CalibrationFactorResource::collection($region->calibrationFactors()->orderBy('method')->orderBy('reference')->orderBy('month')->get())->resolve(),
            'receipt' => Inertia::defer(fn () => $receipt->for($region, $period, $method, $reference)),
            'backfill' => $planner->activeBatch($region),
            'canBackfill' => $firms->hasKey() && $request->user()?->can('backfill', $region) === true,
        ]);
    }
}
