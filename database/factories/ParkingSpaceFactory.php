<?php

namespace Database\Factories;

use App\Models\ParkingSpace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ParkingSpaceFactory extends Factory
{
    protected $model = ParkingSpace::class;

    public function definition(): array
    {
        return [
            'unit_id' => $this->faker->randomNumber(),
            'parking_number' => $this->faker->word(),
            'title' => $this->faker->word(),
            'type' => $this->faker->word(),
            'is_active' => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
