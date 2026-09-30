<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $suite
 * @property string $provider
 * @property string $model
 * @property string $version
 * @property int $cases
 * @property array<string, float|int> $scores
 * @property list<array<string, mixed>> $results
 * @property CarbonImmutable $ran_at
 */
class AiEvaluation extends Model
{
    protected $fillable = ['suite', 'provider', 'model', 'version', 'cases', 'scores', 'results', 'ran_at'];

    protected function casts(): array
    {
        return [
            'cases' => 'integer',
            'scores' => 'array',
            'results' => 'array',
            'ran_at' => 'immutable_datetime',
        ];
    }

    public static function latestFor(string $suite): ?self
    {
        return static::query()->where('suite', $suite)->latest('ran_at')->latest('id')->first();
    }
}
