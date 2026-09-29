<?php

namespace App\Models;

use App\Enums\IngestStatus;
use App\Enums\Instrument;
use Carbon\CarbonImmutable;
use Database\Factories\IngestRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $source
 * @property Instrument|null $instrument
 * @property int|null $region_id
 * @property IngestStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int $rows_read
 * @property int $rows_inserted
 * @property int $rows_skipped
 * @property CarbonImmutable|null $date_from
 * @property CarbonImmutable|null $date_to
 * @property array<string, mixed>|null $meta
 * @property string|null $error
 */
class IngestRun extends Model
{
    /** @use HasFactory<IngestRunFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'instrument',
        'region_id',
        'status',
        'started_at',
        'finished_at',
        'rows_read',
        'rows_inserted',
        'rows_skipped',
        'date_from',
        'date_to',
        'meta',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'instrument' => Instrument::class,
            'status' => IngestStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'date_from' => 'immutable_date',
            'date_to' => 'immutable_date',
            'meta' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function start(string $source, ?Instrument $instrument = null, ?Region $region = null, array $meta = []): self
    {
        return static::create([
            'source' => $source,
            'instrument' => $instrument,
            'region_id' => $region?->id,
            'status' => IngestStatus::Running,
            'started_at' => CarbonImmutable::now(),
            'meta' => $meta,
        ]);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return HasMany<Detection, $this>
     */
    public function detections(): HasMany
    {
        return $this->hasMany(Detection::class);
    }

    public function complete(int $read, int $inserted, int $skipped, ?string $from = null, ?string $to = null): self
    {
        $this->forceFill([
            'status' => IngestStatus::Completed,
            'finished_at' => CarbonImmutable::now(),
            'rows_read' => $read,
            'rows_inserted' => $inserted,
            'rows_skipped' => $skipped,
            'date_from' => $from,
            'date_to' => $to,
        ])->save();

        return $this;
    }

    public function fail(string $error): self
    {
        $this->forceFill([
            'status' => IngestStatus::Failed,
            'finished_at' => CarbonImmutable::now(),
            'error' => mb_substr($error, 0, 4000),
        ])->save();

        return $this;
    }
}
