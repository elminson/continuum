<?php

namespace App\Services\Insights;

use App\Actions\Insights\TriageAlert;
use App\Ai\Agents\AlertAnalyst;
use App\Ai\Evals\AlertAnalystBenchmark;
use App\Enums\HarmonizationMethod;
use App\Enums\IngestStatus;
use App\Enums\ReferenceSensor;
use App\Models\AiEvaluation;
use App\Models\Alert;
use App\Models\AlertTriage;
use App\Models\CalibrationFactor;
use App\Models\Region;
use App\Services\Firms\AreaApiClient;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live figures for the "How it works" page: what the pipeline has actually
 * processed, and the calibration evidence of the best-covered public area.
 * Every number on that page comes from here, none are typed in by hand.
 */
class EngineeringShowcase
{
    public const string CACHE_KEY = 'continuum.engineering';

    public function __construct(
        private readonly RegionSummary $summary,
        private readonly OverlapAnalysis $analysis,
        private readonly TriageAlert $triage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return Cache::flexible(self::cacheKey(), [600, 86400], fn (): array => $this->build());
    }

    /** Labels inside are translated, so each language keeps its own copy. */
    public static function cacheKey(): string
    {
        return self::CACHE_KEY.'.'.app()->getLocale();
    }

    public function refresh(): void
    {
        Cache::putMany([
            self::cacheKey() => $this->build(),
            Repository::FLEXIBLE_CREATED_KEY_PREFIX.self::cacheKey() => now()->getTimestamp(),
        ], 86400);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $global = $this->summary->global();

        $runs = DB::table('ingest_runs')
            ->where('status', IngestStatus::Completed->value)
            ->selectRaw("count(*) filter (where source like 'area:%') as area_windows")
            ->selectRaw("count(*) filter (where source like 'public:%') as public_pulls")
            ->selectRaw('count(*) as runs')
            ->selectRaw('coalesce(sum(rows_read), 0) as rows_read')
            ->selectRaw('coalesce(sum(rows_inserted), 0) as rows_inserted')
            ->selectRaw('coalesce(sum(rows_skipped), 0) as rows_skipped')
            ->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'detections' => (int) $global['detections'],
                'cell_days' => (int) $global['cell_days'],
                'days' => (int) $global['days'],
                'first_date' => $global['first_date'],
                'last_date' => $global['last_date'],
                'modis_detections' => (int) $global['modis']['detections'],
                'viirs_detections' => (int) $global['viirs']['detections'],
                'satellites' => (int) $global['modis']['satellites'] + (int) $global['viirs']['satellites'],
                'public_areas' => Region::query()->preset()->count(),
                'areas' => Region::query()->count(),
                'calibrated_areas' => (int) $global['calibrated_regions'],
                'alerts' => (int) $global['alerts'],
                'ingest_runs' => (int) ($runs->runs ?? 0),
                'area_windows' => (int) ($runs->area_windows ?? 0),
                'public_pulls' => (int) ($runs->public_pulls ?? 0),
                'rows_read' => (int) ($runs->rows_read ?? 0),
                'rows_inserted' => (int) ($runs->rows_inserted ?? 0),
                'rows_skipped' => (int) ($runs->rows_skipped ?? 0),
                'calibration_factors' => CalibrationFactor::query()->count(),
                'calendar_entries' => DB::table('calendar_entries')->count(),
            ],
            'config' => [
                'grid_km' => (float) config('continuum.grid_km'),
                'calendar_cell_deg' => (float) config('continuum.calendar_cell_deg'),
                'bootstrap_samples' => (int) config('continuum.calibration.bootstrap_samples'),
                'holdout_fraction' => (float) config('continuum.calibration.holdout_fraction'),
                'method_version' => (string) config('continuum.calibration.method_version'),
                'window_days' => AreaApiClient::MAX_DAYS,
                'thresholds' => config('continuum.anomalies.thresholds'),
            ],
            'featured' => $this->featured(),
            'ai' => $this->ai(),
        ];
    }

    /**
     * The advisory AI layer: what the alert analyst has read, one real reading
     * exactly as it went in and came out, and its latest benchmark score.
     *
     * @return array<string, mixed>
     */
    private function ai(): array
    {
        $alerts = Alert::query()->with('region')->get();
        AlertTriage::attachTo($alerts);

        $byCause = [];
        $actNow = 0;
        $thin = 0;
        $read = 0;
        $lastReadAt = null;
        $example = null;

        foreach ($alerts as $alert) {
            $triage = $alert->triage;

            if (! $triage instanceof AlertTriage) {
                continue;
            }

            $read++;
            $byCause[$triage->cause] = ($byCause[$triage->cause] ?? 0) + 1;
            $actNow += $triage->attention() === 'act_now' ? 1 : 0;
            $thin += $triage->baseline_reliable === false ? 1 : 0;
            $lastReadAt = $lastReadAt === null || $triage->triaged_at->gt($lastReadAt) ? $triage->triaged_at : $lastReadAt;

            // Prefer a public area, then the strongest reliable departure.
            $rank = [$alert->region->is_preset ? 1 : 0, $triage->baseline_reliable ? 1 : 0, $triage->concern_level, $alert->z_score];

            if ($example === null || $rank > $example['rank']) {
                $example = ['rank' => $rank, 'alert' => $alert, 'triage' => $triage];
            }
        }

        $evaluation = AiEvaluation::latestFor(AlertAnalystBenchmark::SUITE);

        return [
            'model' => $this->triage->model(),
            'provider' => $this->triage->provider(),
            'instructions' => trim((string) (new AlertAnalyst)->instructions()),
            'causes' => AlertAnalyst::CAUSES,
            'attention' => AlertAnalyst::ATTENTION,
            'alerts' => $alerts->count(),
            'read' => $read,
            'act_now' => $actNow,
            'thin_baseline' => $thin,
            'by_cause' => $byCause,
            'last_read_at' => $lastReadAt?->toIso8601String(),
            'example' => $example === null ? null : [
                'region' => $example['alert']->region->name,
                'region_slug' => $example['alert']->region->slug,
                'message' => $example['alert']->message,
                'state' => $this->triage->state($example['alert']),
                'reading' => [
                    'baseline_reliable' => $example['triage']->baseline_reliable,
                    'artifact_risk' => $example['triage']->artifact_probability,
                    'cause' => $example['triage']->cause,
                    'attention' => $example['triage']->attention(),
                    'rationale' => $example['triage']->rationale,
                ],
                'triaged_at' => $example['triage']->triaged_at->toIso8601String(),
            ],
            'evaluation' => $evaluation === null ? null : [
                'model' => $evaluation->model,
                'cases' => $evaluation->cases,
                'scores' => $evaluation->scores,
                'ran_at' => $evaluation->ran_at->toIso8601String(),
                'results' => array_map(fn (array $result) => [
                    'name' => $result['name'],
                    'want' => $result['want'],
                    'got' => $result['got'] === null ? null : array_intersect_key($result['got'], array_flip(['baseline_reliable', 'cause', 'attention'])),
                    'checks' => $result['checks'],
                    'passed' => $result['passed'],
                ], $evaluation->results),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $validation
     * @return array<string, mixed>|null
     */
    private function validation(?array $validation): ?array
    {
        if ($validation === null) {
            return null;
        }

        $number = fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;

        return [
            'train_days' => (int) ($validation['train_days'] ?? 0),
            'test_days' => (int) ($validation['test_days'] ?? 0),
            'holdout' => $validation['holdout'] ?? null,
            'mae' => $number($validation['harmonized']['mae'] ?? null),
            'r2' => $number($validation['harmonized']['r2'] ?? null),
            'bias' => $number($validation['harmonized']['bias'] ?? null),
            'naive_mae' => $number($validation['naive']['mae'] ?? null),
            'naive_r2' => $number($validation['naive']['r2'] ?? null),
            'mae_improvement' => $number($validation['mae_improvement'] ?? null),
            'correlation' => $number($validation['correlation'] ?? null),
        ];
    }

    /**
     * The public area with the longest calibrated overlap, with the evidence
     * behind its transfer functions.
     *
     * @return array<string, mixed>|null
     */
    private function featured(): ?array
    {
        $ratio = CalibrationFactor::query()
            ->whereIn('region_id', Region::query()->preset()->select('id'))
            ->where('method', HarmonizationMethod::Ratio->value)
            ->where('reference', ReferenceSensor::Modis->value)
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->orderByDesc('sample_days')
            ->first();

        if ($ratio === null) {
            return null;
        }

        $region = Region::query()->findOrFail($ratio->region_id);
        $threshold = CalibrationFactor::query()
            ->where('region_id', $region->id)
            ->where('method', HarmonizationMethod::FrpThreshold->value)
            ->where('reference', ReferenceSensor::Modis->value)
            ->where('month', CalibrationFactor::ALL_YEAR)
            ->first();

        $summary = $this->summary->for($region);

        return [
            'slug' => $region->slug,
            'name' => $region->name,
            'color' => $region->color,
            'bbox' => $region->bbox()->toArray(),
            'map_date' => $summary['last_active_date'] ?? $summary['last_date'] ?? null,
            'ratio' => [
                'value' => (float) $ratio->value,
                'ci95' => [(float) $ratio->ci_low, (float) $ratio->ci_high],
                'sample_days' => (int) $ratio->sample_days,
                'overlap' => [$ratio->overlap_from?->toDateString(), $ratio->overlap_to?->toDateString()],
                'validation' => $this->validation($ratio->validation),
            ],
            'threshold' => $threshold === null ? null : [
                'value' => (float) $threshold->value,
                'validation' => $this->validation($threshold->validation),
            ],
            'pairs' => $this->analysis->pairs($region),
            'threshold_curve' => $this->analysis->thresholdCurve($region),
        ];
    }
}
