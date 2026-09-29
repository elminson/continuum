<?php

namespace Database\Factories;

use App\Enums\ConfidenceClass;
use App\Enums\DayNight;
use App\Enums\Instrument;
use App\Enums\Satellite;
use App\Models\Detection;
use App\Models\IngestRun;
use App\Support\Geo\Grid;
use App\Support\Geo\LatLonGrid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Detection>
 */
class DetectionFactory extends Factory
{
    public function definition(): array
    {
        $satellite = fake()->randomElement(Satellite::cases());
        $instrument = $satellite->instrument();
        $acquiredAt = CarbonImmutable::parse(fake()->dateTimeBetween('-30 days', 'now'))->setSecond(0);
        $latitude = fake()->randomFloat(5, -55, 65);
        $longitude = fake()->randomFloat(5, -170, 170);
        $nominal = $instrument->nominalPixelKm();

        return [
            'fingerprint' => md5(fake()->unique()->uuid()),
            'ingest_run_id' => IngestRun::factory(),
            'instrument' => $instrument,
            'satellite' => $satellite,
            'acquired_at' => $acquiredAt,
            'acq_date' => $acquiredAt->toDateString(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'brightness' => fake()->randomFloat(2, 300, 400),
            'brightness_secondary' => fake()->randomFloat(2, 280, 320),
            'scan' => $scan = round($nominal * fake()->randomFloat(2, 1, 2), 2),
            'track' => $track = round($nominal * fake()->randomFloat(2, 1, 1.5), 2),
            'footprint_km2' => round($scan * $track, 4),
            'frp' => fake()->randomFloat(2, 0.5, 250),
            'confidence_raw' => $instrument === Instrument::Modis ? (string) fake()->numberBetween(0, 100) : 'n',
            'confidence' => fake()->randomElement(ConfidenceClass::cases()),
            'daynight' => fake()->randomElement(DayNight::cases()),
            'version' => $instrument === Instrument::Modis ? '6.1NRT' : '2.0NRT',
            'cell_id' => (new Grid(1))->cellIdFor($latitude, $longitude),
            'calendar_cell_id' => (new LatLonGrid(0.25))->cellIdFor($latitude, $longitude),
        ];
    }

    public function at(float $latitude, float $longitude, ?string $date = null): static
    {
        return $this->state(function () use ($latitude, $longitude, $date) {
            $state = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'cell_id' => (new Grid((float) config('continuum.grid_km', 1)))->cellIdFor($latitude, $longitude),
                'calendar_cell_id' => LatLonGrid::fromConfig()->cellIdFor($latitude, $longitude),
            ];

            if ($date !== null) {
                $acquiredAt = CarbonImmutable::parse($date)->setTime(13, 30);
                $state['acquired_at'] = $acquiredAt;
                $state['acq_date'] = $acquiredAt->toDateString();
            }

            return $state;
        });
    }

    public function satellite(Satellite $satellite): static
    {
        return $this->state(fn () => [
            'satellite' => $satellite,
            'instrument' => $satellite->instrument(),
            'version' => $satellite->instrument() === Instrument::Modis ? '6.1NRT' : '2.0NRT',
        ]);
    }

    public function frp(float $frp): static
    {
        return $this->state(fn () => ['frp' => $frp]);
    }
}
