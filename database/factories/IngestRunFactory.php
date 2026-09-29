<?php

namespace Database\Factories;

use App\Enums\IngestStatus;
use App\Enums\Instrument;
use App\Models\IngestRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestRun>
 */
class IngestRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source' => 'test:'.fake()->word(),
            'instrument' => fake()->randomElement(Instrument::cases()),
            'region_id' => null,
            'status' => IngestStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'rows_read' => 0,
            'rows_inserted' => 0,
            'rows_skipped' => 0,
            'meta' => [],
        ];
    }
}
