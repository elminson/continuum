<?php

namespace Database\Factories;

use App\Enums\Satellite;
use App\Models\CellDay;
use App\Support\Geo\Grid;
use App\Support\Geo\LatLonGrid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellDay>
 */
class CellDayFactory extends Factory
{
    public function definition(): array
    {
        $satellite = fake()->randomElement(Satellite::cases());
        $latitude = fake()->randomFloat(5, -55, 65);
        $longitude = fake()->randomFloat(5, -170, 170);
        $detections = fake()->numberBetween(1, 6);

        return [
            'cell_id' => (new Grid(1))->cellIdFor($latitude, $longitude),
            'acq_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'satellite' => $satellite,
            'instrument' => $satellite->instrument(),
            'calendar_cell_id' => (new LatLonGrid(0.25))->cellIdFor($latitude, $longitude),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'detections' => $detections,
            'day_detections' => $detections,
            'night_detections' => 0,
            'low_confidence' => 0,
            'nominal_confidence' => $detections,
            'high_confidence' => 0,
            'frp_sum' => fake()->randomFloat(2, 1, 400),
            'frp_max' => fake()->randomFloat(2, 1, 200),
            'footprint_km2' => fake()->randomFloat(4, 0.14, 4),
        ];
    }

    /**
     * Place the cell-day in the harmonization cell that contains the point.
     */
    public function at(float $latitude, float $longitude, ?string $date = null): static
    {
        return $this->state(fn () => array_filter([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'cell_id' => Grid::fromConfig()->cellIdFor($latitude, $longitude),
            'calendar_cell_id' => LatLonGrid::fromConfig()->cellIdFor($latitude, $longitude),
            'acq_date' => $date,
        ], fn ($value) => $value !== null));
    }

    public function satellite(Satellite $satellite): static
    {
        return $this->state(fn () => [
            'satellite' => $satellite,
            'instrument' => $satellite->instrument(),
        ]);
    }

    public function frp(float $sum, ?float $max = null): static
    {
        return $this->state(fn () => ['frp_sum' => $sum, 'frp_max' => $max ?? $sum]);
    }

    public function detections(int $count): static
    {
        return $this->state(fn () => [
            'detections' => $count,
            'day_detections' => $count,
            'night_detections' => 0,
            'nominal_confidence' => $count,
        ]);
    }
}
