<?php

namespace App\Models;

use App\Support\Geo\BoundingBox;
use Carbon\CarbonImmutable;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $west
 * @property float $south
 * @property float $east
 * @property float $north
 * @property string $color
 * @property bool $is_preset
 * @property CarbonImmutable|null $harmonized_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'west',
        'south',
        'east',
        'north',
        'color',
        'is_preset',
        'harmonized_at',
    ];

    protected function casts(): array
    {
        return [
            'west' => 'float',
            'south' => 'float',
            'east' => 'float',
            'north' => 'float',
            'is_preset' => 'boolean',
            'harmonized_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Region $region): void {
            $region->slug ??= static::uniqueSlug($region->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'region';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<CalendarEntry, $this>
     */
    public function calendarEntries(): HasMany
    {
        return $this->hasMany(CalendarEntry::class);
    }

    /**
     * @return HasMany<CalibrationFactor, $this>
     */
    public function calibrationFactors(): HasMany
    {
        return $this->hasMany(CalibrationFactor::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * @return HasMany<IngestRun, $this>
     */
    public function ingestRuns(): HasMany
    {
        return $this->hasMany(IngestRun::class);
    }

    public function bbox(): BoundingBox
    {
        return new BoundingBox($this->west, $this->south, $this->east, $this->north);
    }

    public function areaKm2(): float
    {
        return $this->bbox()->areaKm2();
    }

    /**
     * @param  Builder<Region>  $query
     */
    #[Scope]
    protected function preset(Builder $query): void
    {
        $query->where('is_preset', true);
    }

    /**
     * @param  Builder<Region>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('is_preset', true);

            if ($user !== null) {
                $query->orWhere('user_id', $user->id);
            }
        });
    }
}
