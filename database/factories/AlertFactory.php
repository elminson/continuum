<?php

namespace Database\Factories;

use App\Enums\AlertSeverity;
use App\Enums\CalendarPeriod;
use App\Enums\HarmonizationMethod;
use App\Enums\ReferenceSensor;
use App\Models\Alert;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'period' => CalendarPeriod::Week,
            'period_start' => now()->startOfWeek()->toDateString(),
            'metric' => 'cell_days',
            'method' => HarmonizationMethod::Ratio,
            'reference' => ReferenceSensor::Modis,
            'observed' => 300,
            'expected' => 100,
            'spread' => 50,
            'z_score' => 4.0,
            'percentile' => 99.0,
            'severity' => AlertSeverity::Warning,
            'climatology_years' => 5,
            'message' => 'Burning activity is 3x the seasonal baseline.',
        ];
    }
}
