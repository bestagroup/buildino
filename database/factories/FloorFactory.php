<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Floor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Floor>
 */
class FloorFactory extends Factory
{
    protected $model = Floor::class;

    public function definition(): array
    {
        $floorNumber = fake()->numberBetween(1, 30);

        return [
            'block_id' => Block::factory(),

            'floor_number' => $floorNumber,

            'title' => "Floor {$floorNumber}",

            'sort_order' => $floorNumber,
        ];
    }
}
