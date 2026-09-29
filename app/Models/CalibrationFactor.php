<?php

namespace App\Models;

use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use Carbon\CarbonImmutable;
use Database\Factories\CalibrationFactorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $region_id
 * @property HarmonizationMethod $method
 * @property ReferenceSensor $reference
 * @property int $month
 * @property string $metric
 * @property float $value
 * @property float|null $ci_low
 * @property float|null $ci_high
 * @property int $sample_days
 * @property CarbonImmutable|null $overlap_from
 * @property CarbonImmutable|null $overlap_to
 * @property array<string, mixed>|null $validation
 * @property string $version
 * @property CarbonImmutable $calibrated_at
 */
class CalibrationFactor extends Model
{
    /** @use HasFactory<CalibrationFactorFactory> */
    use HasFactory;

    public const int ALL_YEAR = 0;

    protected $fillable = [
        'region_id',
        'method',
        'reference',
        'month',
        'metric',
        'value',
        'ci_low',
        'ci_high',
        'sample_days',
        'overlap_from',
        'overlap_to',
        'validation',
        'version',
        'calibrated_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => HarmonizationMethod::class,
            'reference' => ReferenceSensor::class,
            'month' => 'integer',
            'value' => 'float',
            'ci_low' => 'float',
            'ci_high' => 'float',
            'sample_days' => 'integer',
            'overlap_from' => 'immutable_date',
            'overlap_to' => 'immutable_date',
            'validation' => 'array',
            'calibrated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function isAllYear(): bool
    {
        return $this->month === self::ALL_YEAR;
    }
}
