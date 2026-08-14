<?php

namespace Database\Factories;

use App\Models\Complex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complex>
 */
class ComplexFactory extends Factory
{
    protected $model = Complex::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CMP-####'),
            'title' => fake()->company(),

            'province' => fake()->state(),
            'city' => fake()->city(),
            'address' => fake()->address(),
            'postal_code' => fake()->postcode(),

            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),

            'sort_order' => fake()->numberBetween(0, 100),

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
