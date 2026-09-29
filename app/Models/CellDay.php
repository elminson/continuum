<?php

namespace App\Models;

use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Database\Factories\CellDayFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * All detections from one satellite that fell in one harmonization grid
 * cell on one day, collapsed into a single record.

 *
 * @property int $id
 * @property int $cell_id
 * @property CarbonImmutable $acq_date
 * @property Satellite $satellite
 * @property Instrument $instrument
 * @property int $calendar_cell_id
 * @property float $latitude
 * @property float $longitude
 * @property int $detections
 * @property int $day_detections
 * @property int $night_detections
 * @property int $low_confidence
 * @property int $nominal_confidence
 * @property int $high_confidence
 * @property float $frp_sum
 * @property float $frp_max
 * @property float $footprint_km2
 */
class CellDay extends Model
{
    /** @use HasFactory<CellDayFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'cell_id',
        'acq_date',
        'satellite',
        'instrument',
        'calendar_cell_id',
        'latitude',
        'longitude',
        'detections',
        'day_detections',
        'night_detections',
        'low_confidence',
        'nominal_confidence',
        'high_confidence',
        'frp_sum',
        'frp_max',
        'footprint_km2',
    ];

    protected function casts(): array
    {
        return [
            'cell_id' => 'integer',
            'acq_date' => 'immutable_date',
            'satellite' => Satellite::class,
            'instrument' => Instrument::class,
            'calendar_cell_id' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'detections' => 'integer',
            'day_detections' => 'integer',
            'night_detections' => 'integer',
            'low_confidence' => 'integer',
            'nominal_confidence' => 'integer',
            'high_confidence' => 'integer',
            'frp_sum' => 'float',
            'frp_max' => 'float',
            'footprint_km2' => 'float',
        ];
    }

    /**
     * @param  Builder<CellDay>  $query
     */
    #[Scope]
    protected function withinBox(Builder $query, BoundingBox $box): void
    {
        $query->whereBetween('latitude', [$box->south, $box->north])
            ->whereBetween('longitude', [$box->west, $box->east]);
    }

    /**
     * @param  Builder<CellDay>  $query
     */
    #[Scope]
    protected function forInstrument(Builder $query, Instrument $instrument): void
    {
        $query->where('instrument', $instrument->value);
    }

    /**
     * @param  Builder<CellDay>  $query
     */
    #[Scope]
    protected function betweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('acq_date', [$from, $to]);
    }
}
