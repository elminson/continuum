<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Upsert the preset areas of interest declared in config/continuum.php.
     */
    public function run(): void
    {
        foreach (config('continuum.presets', []) as $preset) {
            Region::query()->updateOrCreate(
                ['slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'description' => $preset['description'] ?? null,
                    'west' => $preset['bbox'][0],
                    'south' => $preset['bbox'][1],
                    'east' => $preset['bbox'][2],
                    'north' => $preset['bbox'][3],
                    'color' => $preset['color'] ?? '#f97316',
                    'is_preset' => true,
                    'user_id' => null,
                ],
            );
        }
    }
}
