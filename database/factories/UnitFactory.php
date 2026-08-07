<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'floor_id' => $this->faker->randomNumber(),
            'unit_number' => $this->faker->word(),
            'title' => $this->faker->word(),
            'area' => $this->faker->randomFloat(),
            'bedrooms' => $this->faker->randomNumber(),
            'usage_type' => $this->faker->word(),
            'ownership_status' => $this->faker->word(),
            'is_active' => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
