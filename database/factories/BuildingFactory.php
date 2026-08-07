<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'complex_id' => $this->faker->randomNumber(),
            'code' => $this->faker->word(),
            'title' => $this->faker->word(),
            'building_number' => $this->faker->word(),
            'floors_count' => $this->faker->randomNumber(),
            'units_count' => $this->faker->randomNumber(),
            'parking_count' => $this->faker->randomNumber(),
            'storage_count' => $this->faker->randomNumber(),
            'construction_year' => $this->faker->randomNumber(),
            'is_active' => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
