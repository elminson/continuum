<?php

namespace Database\Factories;

use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\CalibrationFactor;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalibrationFactor>
 */
class CalibrationFactorFactory extends Factory
{
    public function definition(): array
    {
        $value = fake()->randomFloat(4, 0.15, 0.4);

        return [
            'region_id' => Region::factory(),
            'method' => HarmonizationMethod::Ratio,
            'reference' => ReferenceSensor::Modis,
            'month' => CalibrationFactor::ALL_YEAR,
            'metric' => 'cell_days',
            'value' => $value,
            'ci_low' => $value * 0.9,
            'ci_high' => $value * 1.1,
            'sample_days' => fake()->numberBetween(5, 300),
            'overlap_from' => '2012-01-20',
            'overlap_to' => now()->toDateString(),
            'validation' => ['n' => 10, 'bias' => 0.1, 'mae' => 2.0, 'rmse' => 2.5, 'r2' => 0.9, 'mape' => 0.1],
            'version' => config('continuum.calibration.method_version'),
            'calibrated_at' => now(),
        ];
    }
}
