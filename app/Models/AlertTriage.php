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
 * @property array<string, float>|null $cause_probabilities
 * @property bool|null $baseline_reliable
 * @property string|null $rationale
 * @property float|null $cause_confidence
 * @property float $artifact_probability
 * @property float|null $concern_score
 * @property int $concern_level
 * @property string $provider
 * @property string $model
 * @property CarbonImmutable $triaged_at
 */
class AlertTriage extends Model
{
    /** Attention levels in order; stored as their index in concern_level. */
    public const array ATTENTION_LEVELS = ['no_action', 'watch', 'act_now'];

    protected $fillable = [
        'region_id',
        'period',
        'period_start',
        'method',
        'reference',
        'input_hash',
        'baseline_reliable',
        'cause',
        'cause_probabilities',
        'cause_confidence',
        'artifact_probability',
        'concern_score',
        'concern_level',
        'rationale',
        'provider',
        'model',
        'triaged_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'cause_probabilities' => 'array',
            'baseline_reliable' => 'boolean',
            'cause_confidence' => 'float',
            'artifact_probability' => 'float',
            'concern_score' => 'float',
            'concern_level' => 'integer',
            'triaged_at' => 'immutable_datetime',
        ];
    }

    public function attention(): string
    {
        return self::ATTENTION_LEVELS[$this->concern_level] ?? 'watch';
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
