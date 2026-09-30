<?php

namespace App\Ai\Evals;

use App\Actions\Insights\TriageAlert;
use App\Enums\AlertSeverity;
use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\AiEvaluation;
use App\Models\Alert;
use App\Models\Region;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Labelled cases for the alert analyst, rendered through exactly the same
 * state template the live readings use. The expected answers follow the
 * rubric in AlertAnalyst::instructions(); the benchmark checks the model
 * applies it, so a model or prompt change that reads alerts worse shows up
 * as a lower score on the site instead of going unnoticed.
 */
class AlertAnalystBenchmark
{
    public const string SUITE = 'alert-analyst';

    public function __construct(private readonly TriageAlert $triage) {}

    /**
     * @return list<array{name: string, area: string, km2: int, period: string, start: string, observed: float, expected: float, spread: float, z: float, percentile: float, years: int, want: array{baseline_reliable: bool, cause: string, attention: string}}>
     */
    public function cases(): array
    {
        $case = fn (string $name, string $area, int $km2, string $period, string $start, float $observed, float $expected, float $spread, float $z, float $percentile, int $years, bool $reliable, string $cause, string $attention) => [
            'name' => $name, 'area' => $area, 'km2' => $km2, 'period' => $period, 'start' => $start,
            'observed' => $observed, 'expected' => $expected, 'spread' => $spread, 'z' => $z, 'percentile' => $percentile, 'years' => $years,
            'want' => ['baseline_reliable' => $reliable, 'cause' => $cause, 'attention' => $attention],
        ];

        return [
            $case('Sample week against an empty history', 'Gran Chaco', 3300000, 'week', '2026-09-21', 2751, 0, 1, 2751, 100, 1, false, 'data_gap', 'no_action'),
            $case('Three thin years, median zero', 'Northern Australia savannas', 4300000, 'month', '2025-10-01', 410, 0, 1, 410, 100, 3, false, 'data_gap', 'no_action'),
            $case('Four years of record, small median', 'Dominican Republic', 70000, 'week', '2024-03-18', 22, 3, 2, 9.5, 100, 4, false, 'data_gap', 'no_action'),
            $case('Seven times a long-record median', 'Santo Domingo', 174913, 'week', '2014-07-21', 89, 12, 7, 10.4, 100, 25, true, 'outbreak', 'act_now'),
            $case('Thirteen times a long-record median', 'Cuba', 1154000, 'month', '2026-08-01', 774, 58, 31, 23.0, 100, 25, true, 'outbreak', 'act_now'),
            $case('Five times the median in peak season', 'Cerrado & Amazon Arc', 4466000, 'month', '2024-09-01', 61200, 12100, 5300, 9.3, 100, 22, true, 'outbreak', 'act_now'),
            $case('A strong but ordinary fire season', 'Gran Chaco', 3300000, 'month', '2023-09-01', 8900, 5100, 1400, 2.7, 94, 24, true, 'seasonal', 'watch'),
            $case('A busier week than usual', 'Borneo & Sumatra', 4300000, 'week', '2019-09-09', 2350, 1300, 600, 1.8, 88, 18, true, 'seasonal', 'watch'),
            $case('Slightly above a long-record median', 'Cuba', 1154000, 'month', '2019-04-01', 95, 58, 31, 1.6, 74, 25, true, 'seasonal', 'no_action'),
            $case('Twice the median, strong z-score', 'Northern Australia savannas', 4300000, 'week', '2021-08-02', 3100, 1450, 320, 5.2, 99, 20, true, 'seasonal', 'watch'),
        ];
    }

    /**
     * Run every case through the live reading code and store the result.
     */
    public function run(?callable $onCase = null): AiEvaluation
    {
        $results = [];

        foreach ($this->cases() as $case) {
            $state = $this->triage->state($this->alertFor($case));
            $started = microtime(true);

            try {
                $got = $this->triage->read($state);
                $error = null;
            } catch (Throwable $exception) {
                $got = null;
                $error = mb_substr($exception->getMessage(), 0, 200);
            }

            $checks = [
                'baseline_reliable' => $got !== null && $got['baseline_reliable'] === $case['want']['baseline_reliable'],
                'cause' => $got !== null && $got['cause'] === $case['want']['cause'],
                'attention' => $got !== null && $got['attention'] === $case['want']['attention'],
            ];

            $results[] = [
                'name' => $case['name'],
                'state' => $state,
                'want' => $case['want'],
                'got' => $got,
                'checks' => $checks,
                'passed' => ! in_array(false, $checks, true),
                'seconds' => round(microtime(true) - $started, 2),
                'error' => $error,
            ];

            if ($onCase !== null) {
                $onCase(end($results));
            }
        }

        $count = count($results);
        $share = fn (string $check) => $count === 0 ? 0.0 : round(count(array_filter($results, fn ($r) => $r['checks'][$check])) / $count, 3);
        $seconds = array_column($results, 'seconds');
        sort($seconds);

        return AiEvaluation::query()->create([
            'suite' => self::SUITE,
            'provider' => $this->triage->provider(),
            'model' => $this->triage->model(),
            'version' => TriageAlert::VERSION,
            'cases' => $count,
            'scores' => [
                'passed' => count(array_filter($results, fn ($r) => $r['passed'])),
                'baseline_reliable' => $share('baseline_reliable'),
                'cause' => $share('cause'),
                'attention' => $share('attention'),
                'median_seconds' => $seconds === [] ? null : $seconds[intdiv(count($seconds), 2)],
            ],
            'results' => $results,
            'ran_at' => now(),
        ]);
    }

    /**
     * @param  array{name: string, area: string, km2: int, period: string, start: string, observed: float, expected: float, spread: float, z: float, percentile: float, years: int, want: array<string, mixed>}  $case
     */
    private function alertFor(array $case): Alert
    {
        // A square box of the right area near the equator; only its size is shown to the model.
        $side = sqrt($case['km2']) / 111.32;
        $region = new Region(['name' => $case['area'], 'west' => 0, 'south' => 0, 'east' => $side, 'north' => $side]);

        $alert = new Alert([
            'period' => CalendarPeriod::from($case['period']),
            'period_start' => CarbonImmutable::parse($case['start']),
            'method' => HarmonizationMethod::Ratio,
            'reference' => ReferenceSensor::Modis,
            'observed' => $case['observed'],
            'expected' => $case['expected'],
            'spread' => $case['spread'],
            'z_score' => $case['z'],
            'percentile' => $case['percentile'],
            'severity' => AlertSeverity::fromZScore($case['z']) ?? AlertSeverity::Watch,
            'climatology_years' => $case['years'],
            'message' => $case['name'],
        ]);
        $alert->setRelation('region', $region);

        return $alert;
    }
}
