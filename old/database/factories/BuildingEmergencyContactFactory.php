<?php

namespace Database\Factories;

use App\Models\BuildingEmergencyContact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BuildingEmergencyContactFactory extends Factory
{
    protected $model = BuildingEmergencyContact::class;

    public function definition(): array
    {
        return [
            'building_id' => $this->faker->randomNumber(),
            'title' => $this->faker->word(),
            'phone' => $this->faker->phoneNumber(),
            'description' => $this->faker->text(),
            'sort_order' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
