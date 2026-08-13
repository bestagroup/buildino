<?php

namespace Database\Factories;

use App\Models\Floor;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'floor_id' => Floor::factory(),

            'unit_number' => fake()->unique()->numerify('###'),

            'title' => fake()->optional()->words(2, true),

            'area' => fake()->randomFloat(
                2,
                40,
                300
            ),

            'bedrooms' => fake()->numberBetween(0, 6),

            'usage_type' => 'residential',

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
