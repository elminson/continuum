<?php

namespace App\Actions\Insights;

use App\Ai\Agents\AlertAnalyst;
use App\Enums\CalendarPeriod;
use App\Enums\ReferenceSensor;
use App\Models\Alert;
use App\Models\AlertTriage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use UnexpectedValueException;

/**
 * Asks the alert analyst (an open-weights model we host, through the Laravel
 * AI SDK) for an advisory reading of one flagged period. The statistics decide
 * what is flagged; the reading only helps a person understand it.
 */
class TriageAlert
{
    /** Bump when the instructions, schema or state wording change, to re-read every alert. */
    public const string VERSION = '2026-09-30.analyst';

    public function state(Alert $alert): string
    {
        $alert->loadMissing('region');

        $label = match ($alert->period) {
            CalendarPeriod::Day => $alert->period_start->format('j M Y'),
            CalendarPeriod::Week => 'the week of '.$alert->period_start->format('j M Y'),
            CalendarPeriod::Month => $alert->period_start->format('F Y'),
        };
        $scale = $alert->reference === ReferenceSensor::Modis ? 'MODIS-equivalent' : 'VIIRS-equivalent';
        $multiple = $alert->expected > 0 ? number_format($alert->observed / $alert->expected, 1).' times the median' : 'far above the median';

        return implode(' ', [
            sprintf('Satellite fire record for %s (%s km²), %s.', $alert->region->name, number_format($alert->region->areaKm2()), $label),
            sprintf('Observed: %s %s active 1 km cell-days with fire.', number_format($alert->observed), $scale),
            sprintf('The same %s in %d other years: median %s, robust spread %s.', $alert->period->value, $alert->climatology_years, number_format($alert->expected), number_format($alert->spread)),
            sprintf('That is %s, z-score %s, %s percentile.', $multiple, number_format($alert->z_score, 1), number_format($alert->percentile, 0)),
            sprintf('The historical baseline has %d years of observations for this %s.', $alert->climatology_years, $alert->period->value),
        ]);
    }

    public function hash(Alert $alert): string
    {
        return sha1($this->state($alert).'|'.self::VERSION.'|'.$this->model());
    }

    public function provider(): string
    {
        return (string) config('continuum.analyst.provider', 'ollama');
    }

    public function model(): string
    {
        return (string) config('continuum.analyst.model', 'qwen2.5:7b-instruct');
    }

    /**
     * @return array{baseline_reliable: bool, artifact_risk: float, cause: string, attention: string, rationale: string}
     */
    public function read(string $state): array
    {
        $response = (new AlertAnalyst)->prompt($state, provider: $this->provider(), model: $this->model());

        if (! $response instanceof StructuredAgentResponse) {
            throw new UnexpectedValueException('The analyst did not return structured output.');
        }

        $data = $response->toArray();
        $cause = (string) ($data['cause'] ?? '');
        $attention = (string) ($data['attention'] ?? '');

        if (! array_key_exists($cause, AlertAnalyst::CAUSES) || ! in_array($attention, AlertTriage::ATTENTION_LEVELS, true)) {
            throw new UnexpectedValueException('The analyst answered outside its schema.');
        }

        return [
            'baseline_reliable' => (bool) ($data['baseline_reliable'] ?? false),
            'artifact_risk' => max(0.0, min(1.0, (float) ($data['artifact_risk'] ?? 0))),
            'cause' => $cause,
            'attention' => $attention,
            'rationale' => trim(mb_substr((string) ($data['rationale'] ?? ''), 0, 400)),
        ];
    }

    public function __invoke(Alert $alert): AlertTriage
    {
        $reading = $this->read($this->state($alert));

        return AlertTriage::query()->updateOrCreate(
            [
                'region_id' => $alert->region_id,
                'period' => $alert->period->value,
                'period_start' => $alert->period_start->toDateString(),
                'method' => $alert->method->value,
                'reference' => $alert->reference->value,
            ],
            [
                'input_hash' => $this->hash($alert),
                'baseline_reliable' => $reading['baseline_reliable'],
                'cause' => $reading['cause'],
                'cause_probabilities' => null,
                'cause_confidence' => null,
                'artifact_probability' => round($reading['artifact_risk'], 4),
                'concern_score' => null,
                'concern_level' => (int) array_search($reading['attention'], AlertTriage::ATTENTION_LEVELS, true),
                'rationale' => $reading['rationale'],
                'provider' => $this->provider(),
                'model' => $this->model(),
                'triaged_at' => now(),
            ],
        );
    }
}
