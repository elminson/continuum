<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        $west = fake()->randomFloat(3, -170, 160);
        $south = fake()->randomFloat(3, -60, 60);
        $name = fake()->unique()->city().' fire district';

        return [
            'user_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'west' => $west,
            'south' => $south,
            'east' => $west + fake()->randomFloat(3, 1, 15),
            'north' => $south + fake()->randomFloat(3, 1, 15),
            'color' => fake()->hexColor(),
            'is_preset' => false,
        ];
    }

    public function preset(): static
    {
        return $this->state(fn () => ['is_preset' => true, 'user_id' => null]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id, 'is_preset' => false]);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    public function withBox(array $bbox): static
    {
        return $this->state(fn () => [
            'west' => $bbox[0],
            'south' => $bbox[1],
            'east' => $bbox[2],
            'north' => $bbox[3],
        ]);
    }
}
