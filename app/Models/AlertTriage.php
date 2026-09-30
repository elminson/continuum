<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $region_id
 * @property string $period
 * @property CarbonImmutable $period_start
 * @property string $method
 * @property string $reference
 * @property string $input_hash
 * @property string $cause
 * @property array<string, float> $cause_probabilities
 * @property float|null $cause_confidence
 * @property float $artifact_probability
 * @property float $concern_score
 * @property int $concern_level
 * @property string $provider
 * @property string $model
 * @property CarbonImmutable $triaged_at
 */
class AlertTriage extends Model
{
    public const array CAUSES = [
        'seasonal' => 'The usual burning season for this place, only stronger than typical.',
        'outbreak' => 'An exceptional outbreak on the ground, such as drought, heat or land clearing.',
        'data_gap' => 'A gap or change in the historical record rather than a change on the ground.',
        'sensor' => 'A change in which satellites or sensors observed the area.',
    ];

    public const array CONCERN_LEVELS = ['No action needed', 'Keep watching', 'Act now'];

    protected $fillable = [
        'region_id',
        'period',
        'period_start',
        'method',
        'reference',
        'input_hash',
        'cause',
        'cause_probabilities',
        'cause_confidence',
        'artifact_probability',
        'concern_score',
        'concern_level',
        'provider',
        'model',
        'triaged_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'cause_probabilities' => 'array',
            'cause_confidence' => 'float',
            'artifact_probability' => 'float',
            'concern_score' => 'float',
            'concern_level' => 'integer',
            'triaged_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public static function keyFor(Alert $alert): string
    {
        return implode('|', [$alert->region_id, $alert->period->value, $alert->period_start->toDateString(), $alert->method->value, $alert->reference->value]);
    }

    public function key(): string
    {
        return implode('|', [$this->region_id, $this->period, $this->period_start->toDateString(), $this->method, $this->reference]);
    }

    /**
     * Load the reading of each alert onto its "triage" relation.
     *
     * @param  iterable<Alert>  $alerts
     */
    public static function attachTo(iterable $alerts): void
    {
        $alerts = collect($alerts);

        if ($alerts->isEmpty()) {
            return;
        }

        $triages = static::query()
            ->whereIn('region_id', $alerts->pluck('region_id')->unique()->values())
            ->whereIn('period_start', $alerts->map(fn (Alert $alert) => $alert->period_start->toDateString())->unique()->values())
            ->get()
            ->keyBy(fn (self $triage) => $triage->key());

        foreach ($alerts as $alert) {
            $alert->setRelation('triage', $triages->get(static::keyFor($alert)));
        }
    }
}
