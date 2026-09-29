<?php

namespace Database\Factories;

use App\Enums\CalendarPeriod;
use App\Enums\Instrument;
use App\Models\CalendarEntry;
use App\Models\Region;
use App\Support\Stats\FrpHistogram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEntry>
 */
class CalendarEntryFactory extends Factory
{
    public function definition(): array
    {
        $cellDays = fake()->numberBetween(0, 500);
        $histogram = FrpHistogram::empty();
        $histogram[9] = $cellDays;

        return [
            'region_id' => Region::factory(),
            'period' => CalendarPeriod::Day,
            'period_start' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'instrument' => fake()->randomElement(Instrument::cases()),
            'detections' => $cellDays * 2,
            'active_cells' => $cellDays,
            'cell_days' => $cellDays,
            'frp_sum' => $cellDays * 12.5,
            'frp_max' => 150,
            'footprint_km2' => $cellDays * 0.5,
            'day_detections' => $cellDays * 2,
            'night_detections' => 0,
            'low_confidence' => 0,
            'nominal_confidence' => $cellDays * 2,
            'high_confidence' => 0,
            'active_days' => $cellDays > 0 ? 1 : 0,
            'coverage_days' => 1,
            'frp_histogram' => $histogram,
        ];
    }

    public function daily(string $date, Instrument $instrument, int $cellDays): static
    {
        return $this->state(function () use ($date, $instrument, $cellDays) {
            $histogram = FrpHistogram::empty();
            $histogram[9] = $cellDays;

            return [
                'period' => CalendarPeriod::Day,
                'period_start' => $date,
                'instrument' => $instrument,
                'detections' => $cellDays * ($instrument === Instrument::Viirs ? 4 : 1),
                'active_cells' => $cellDays,
                'cell_days' => $cellDays,
                'frp_sum' => $cellDays * 12.5,
                'active_days' => $cellDays > 0 ? 1 : 0,
                'coverage_days' => 1,
                'frp_histogram' => $histogram,
            ];
        });
    }

    public function weekly(string $mondayDate, Instrument $instrument, int $cellDays): static
    {
        return $this->daily($mondayDate, $instrument, $cellDays)->state(fn () => [
            'period' => CalendarPeriod::Week,
            'active_days' => $cellDays > 0 ? 7 : 0,
            'coverage_days' => 7,
        ]);
    }

    public function monthly(string $firstOfMonth, Instrument $instrument, int $cellDays): static
    {
        return $this->daily($firstOfMonth, $instrument, $cellDays)->state(fn () => [
            'period' => CalendarPeriod::Month,
            'active_days' => $cellDays > 0 ? 30 : 0,
            'coverage_days' => 30,
        ]);
    }

    public function forRegion(Region $region): static
    {
        return $this->state(fn () => ['region_id' => $region->id]);
    }
}
