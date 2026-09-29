<?php

namespace App\Models;

use App\Enums\CalendarPeriod;
use App\Enums\Instrument;
use Carbon\CarbonImmutable;
use Database\Factories\CalendarEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Native (un-harmonized) burning activity of one instrument in one region
 * for one calendar period. Harmonized values are derived from these rows
 * plus the region's calibration factors.

 *
 * @property int $id
 * @property int $region_id
 * @property CalendarPeriod $period
 * @property CarbonImmutable $period_start
 * @property Instrument $instrument
 * @property int $detections
 * @property int $active_cells
 * @property int $cell_days
 * @property float $frp_sum
 * @property float $frp_max
 * @property float $footprint_km2
 * @property int $day_detections
 * @property int $night_detections
 * @property int $low_confidence
 * @property int $nominal_confidence
 * @property int $high_confidence
 * @property int $active_days
 * @property int $coverage_days
 * @property list<int>|null $frp_histogram
 */
class CalendarEntry extends Model
{
    /** @use HasFactory<CalendarEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'region_id',
        'period',
        'period_start',
        'instrument',
        'detections',
        'active_cells',
        'cell_days',
        'frp_sum',
        'frp_max',
        'footprint_km2',
        'day_detections',
        'night_detections',
        'low_confidence',
        'nominal_confidence',
        'high_confidence',
        'active_days',
        'coverage_days',
        'frp_histogram',
    ];

    protected function casts(): array
    {
        return [
            'period' => CalendarPeriod::class,
            'period_start' => 'immutable_date',
            'instrument' => Instrument::class,
            'detections' => 'integer',
            'active_cells' => 'integer',
            'cell_days' => 'integer',
            'frp_sum' => 'float',
            'frp_max' => 'float',
            'footprint_km2' => 'float',
            'day_detections' => 'integer',
            'night_detections' => 'integer',
            'low_confidence' => 'integer',
            'nominal_confidence' => 'integer',
            'high_confidence' => 'integer',
            'active_days' => 'integer',
            'coverage_days' => 'integer',
            'frp_histogram' => 'array',
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
