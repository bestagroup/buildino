<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    protected $model = Block::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),

            'title' => fake()->bothify('Block-##'),

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
