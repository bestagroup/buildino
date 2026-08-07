<?php

namespace Database\Factories;

use App\Models\UnitResident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UnitResidentFactory extends Factory
{
    protected $model = UnitResident::class;

    public function definition(): array
    {
        return [
            'unit_id' => $this->faker->randomNumber(),
            'user_id' => $this->faker->randomNumber(),
            'resident_type' => $this->faker->word(),
            'ownership_percentage' => $this->faker->randomFloat(),
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now(),
            'is_primary' => $this->faker->boolean(),
            'is_active' => $this->faker->boolean(),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
