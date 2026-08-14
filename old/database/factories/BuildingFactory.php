<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Complex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'complex_id' => Complex::factory(),

            'code' => fake()->unique()->bothify('BLD-####'),
            'title' => fake()->company(),
            'building_number' => fake()->bothify('B-##'),

            'floors_count' => fake()->numberBetween(1, 30),
            'units_count' => fake()->numberBetween(1, 200),
            'parking_count' => fake()->numberBetween(0, 200),
            'storage_count' => fake()->numberBetween(0, 200),

            'construction_year' => fake()->numberBetween(1350, 1405),

            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
