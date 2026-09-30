<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads one statistically flagged period the way a fire-data analyst would
 * triage it. It runs on an open-weights model we host ourselves, answers only
 * through the JSON schema below (enums, a bounded number, one sentence), at
 * temperature 0 so the same numbers give the same reading. It never decides
 * what is flagged and never changes a number.
 */
#[Temperature(0)]
#[Timeout(180)]
class AlertAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    public const array CAUSES = [
        'seasonal' => 'The usual fire season for this place, only stronger than typical.',
        'outbreak' => 'Far beyond anything typical in a long record: an exceptional event on the ground.',
        'data_gap' => 'The historical baseline is too thin to judge the value.',
        'sensor' => 'A change in which satellites or sensors observed the area.',
    ];

    public const array ATTENTION = [
        'no_action' => 'No action needed',
        'watch' => 'Keep watching',
        'act_now' => 'Act now',
    ];

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You are a fire-data analyst reviewing a period that an automatic statistical screen flagged as unusual in a
        harmonized MODIS/VIIRS satellite fire record. Judge only from the numbers given; do not assume anything else.

        - baseline_reliable: false when the comparison rests on fewer than 5 years of observations or a median of zero.
        - artifact_risk: probability from 0 to 1 that the flag comes from the record rather than from real fire on the ground.
        - cause: seasonal = the usual season, only stronger; outbreak = far beyond anything typical in a long record;
          data_gap = the baseline is too thin to judge; sensor = a satellite or sensor change.
        - attention: act_now only when the baseline is reliable, the value is at least 3 times the median and the
          z-score is at least 4; watch when the baseline is reliable and the value is clearly but moderately above
          typical; no_action when the baseline is unreliable or the value is only slightly above typical.
        - rationale: one plain sentence for a land manager that cites the numbers behind your reading.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'baseline_reliable' => $schema->boolean()->required(),
            'artifact_risk' => $schema->number()->min(0)->max(1)->required(),
            'cause' => $schema->string()->enum(array_keys(self::CAUSES))->required(),
            'attention' => $schema->string()->enum(array_keys(self::ATTENTION))->required(),
            'rationale' => $schema->string()->max(280)->required(),
        ];
    }
}
