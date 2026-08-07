<?php

namespace Database\Factories;

use App\Models\UnitGuest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UnitGuestFactory extends Factory
{
    protected $model = UnitGuest::class;

    public function definition(): array
    {
        return [
            'unit_id' => $this->faker->randomNumber(),
            'registered_by' => $this->faker->randomNumber(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'mobile' => $this->faker->word(),
            'national_code' => $this->faker->word(),
            'vehicle_number' => $this->faker->word(),
            'expected_entry_at' => Carbon::now(),
            'expected_exit_at' => Carbon::now(),
            'entry_at' => Carbon::now(),
            'exit_at' => Carbon::now(),
            'status' => $this->faker->word(),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
