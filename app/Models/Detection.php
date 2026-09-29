<?php

namespace App\Models;

use App\Enums\ConfidenceClass;
use App\Enums\DayNight;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Database\Factories\DetectionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $fingerprint
 * @property int $ingest_run_id
 * @property Instrument $instrument
 * @property Satellite $satellite
 * @property CarbonImmutable $acquired_at
 * @property CarbonImmutable $acq_date
 * @property float $latitude
 * @property float $longitude
 * @property float $brightness
 * @property float|null $brightness_secondary
 * @property float $scan
 * @property float $track
 * @property float $footprint_km2
 * @property float $frp
 * @property string $confidence_raw
 * @property ConfidenceClass $confidence
 * @property DayNight $daynight
 * @property string $version
 * @property int $cell_id
 * @property int $calendar_cell_id
 */
class Detection extends Model
{
    /** @use HasFactory<DetectionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'fingerprint',
        'ingest_run_id',
        'instrument',
        'satellite',
        'acquired_at',
        'acq_date',
        'latitude',
        'longitude',
        'brightness',
        'brightness_secondary',
        'scan',
        'track',
        'footprint_km2',
        'frp',
        'confidence_raw',
        'confidence',
        'daynight',
        'version',
        'cell_id',
        'calendar_cell_id',
    ];

    protected function casts(): array
    {
        return [
            'instrument' => Instrument::class,
            'satellite' => Satellite::class,
            'acquired_at' => 'immutable_datetime',
            'acq_date' => 'immutable_date',
            'latitude' => 'float',
            'longitude' => 'float',
            'brightness' => 'float',
            'brightness_secondary' => 'float',
            'scan' => 'float',
            'track' => 'float',
            'footprint_km2' => 'float',
            'frp' => 'float',
            'confidence' => ConfidenceClass::class,
            'daynight' => DayNight::class,
            'cell_id' => 'integer',
            'calendar_cell_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<IngestRun, $this>
     */
    public function ingestRun(): BelongsTo
    {
        return $this->belongsTo(IngestRun::class);
    }

    /**
     * @param  Builder<Detection>  $query
     */
    #[Scope]
    protected function withinBox(Builder $query, BoundingBox $box): void
    {
        $query->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east]);
    }

    /**
     * @param  Builder<Detection>  $query
     */
    #[Scope]
    protected function onDate(Builder $query, string $date): void
    {
        $query->where('acq_date', $date);
    }

    /**
     * @param  Builder<Detection>  $query
     */
    #[Scope]
    protected function forInstrument(Builder $query, Instrument $instrument): void
    {
        $query->where('instrument', $instrument->value);
    }
}
