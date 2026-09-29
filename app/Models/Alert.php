<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use Carbon\CarbonImmutable;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $region_id
 * @property CalendarPeriod $period
 * @property CarbonImmutable $period_start
 * @property string $metric
 * @property HarmonizationMethod $method
 * @property ReferenceSensor $reference
 * @property float $observed
 * @property float $expected
 * @property float $spread
 * @property float $z_score
 * @property float $percentile
 * @property AlertSeverity $severity
 * @property int $climatology_years
 * @property string $message
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    protected $fillable = [
        'region_id',
        'period',
        'period_start',
        'metric',
        'method',
        'reference',
        'observed',
        'expected',
        'spread',
        'z_score',
        'percentile',
        'severity',
        'climatology_years',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'period' => CalendarPeriod::class,
            'period_start' => 'immutable_date',
            'method' => HarmonizationMethod::class,
            'reference' => ReferenceSensor::class,
            'observed' => 'float',
            'expected' => 'float',
            'spread' => 'float',
            'z_score' => 'float',
            'percentile' => 'float',
            'severity' => AlertSeverity::class,
            'climatology_years' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
