<?php

namespace App\Actions\Insights;

use App\Enums\CalendarPeriod;
use App\Enums\ReferenceSensor;
use App\Models\Alert;
use App\Models\AlertTriage;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Classification\Score;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Laravel\Ai\Responses\Data\ScoreAnswer;
use UnexpectedValueException;

/**
 * Asks a decision model (Laya, through the Laravel AI SDK) for an advisory
 * reading of one flagged period: its most likely cause, the risk that it is
 * a data artifact, and how much attention it deserves. The statistics decide
 * what is flagged; this only helps a person read it.
 */
class TriageAlert
{
    /** Bump when the questions or the wording of the state change, to re-read every alert. */
    public const string VERSION = '2026-09-30';

    public function state(Alert $alert): string
    {
        $alert->loadMissing('region');

        $label = match ($alert->period) {
            CalendarPeriod::Day => $alert->period_start->format('j M Y'),
            CalendarPeriod::Week => 'the week of '.$alert->period_start->format('j M Y'),
            CalendarPeriod::Month => $alert->period_start->format('F Y'),
        };
        $scale = $alert->reference === ReferenceSensor::Modis ? 'MODIS-equivalent' : 'VIIRS-equivalent';
        $multiple = $alert->expected > 0 ? number_format($alert->observed / $alert->expected, 1).' times' : 'far above';

        return implode(' ', [
            sprintf('Satellite fire record for %s (%s km²), %s.', $alert->region->name, number_format($alert->region->areaKm2()), $label),
            sprintf('Observed: %s %s active 1 km cell-days with fire.', number_format($alert->observed), $scale),
            sprintf('The same %s in %d other years: median %s, robust spread %s.', $alert->period->value, $alert->climatology_years, number_format($alert->expected), number_format($alert->spread)),
            sprintf('That is %s the median, z-score %s, %s percentile.', $multiple, number_format($alert->z_score, 1), number_format($alert->percentile, 0)),
            sprintf('The historical baseline has %d years of observations for this %s.', $alert->climatology_years, $alert->period->value),
        ]);
    }

    public function hash(Alert $alert): string
    {
        return sha1($this->state($alert).'|'.self::VERSION);
    }

    /**
     * @param  string|list<string>|null  $provider  defaults to ai.default_for_classification (a failover list)
     */
    public function __invoke(Alert $alert, string|array|null $provider = null): AlertTriage
    {
        $provider ??= config('ai.default_for_classification');

        $result = Classification::of($this->state($alert))
            ->questions([
                'cause' => new Choice('What most likely explains this unusual fire activity?', AlertTriage::CAUSES),
                'artifact' => new Boolean('Is this unusual value more likely an artifact of the historical record than a real change in fire activity on the ground?'),
                'concern' => new Score('How much attention should a land or fire manager give this period?', AlertTriage::CONCERN_LEVELS),
            ])
            ->timeout(60)
            ->classify($provider);

        $cause = $result['cause'];
        $artifact = $result['artifact'];
        $concern = $result['concern'];

        if (! $cause instanceof ChoiceAnswer || ! $artifact instanceof BooleanAnswer || ! $concern instanceof ScoreAnswer) {
            throw new UnexpectedValueException('The decision model returned answers of the wrong type.');
        }

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
                'cause' => $cause->choice,
                'cause_probabilities' => array_map(fn ($p) => round((float) $p, 4), $cause->probabilities),
                'cause_confidence' => $cause->confidence,
                'artifact_probability' => round($artifact->probability, 4),
                'concern_score' => round($concern->score, 4),
                'concern_level' => $concern->level(),
                'provider' => $result->meta->provider,
                'model' => (string) $result->meta->model,
                'triaged_at' => now(),
            ],
        );
    }
}
