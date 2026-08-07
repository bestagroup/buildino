<?php

namespace Database\Factories;

use App\Models\ResidentHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ResidentHistoryFactory extends Factory
{
    protected $model = ResidentHistory::class;

    public function definition(): array
    {
        return [
            'unit_resident_id' => $this->faker->randomNumber(),
            'unit_id' => $this->faker->randomNumber(),
            'user_id' => $this->faker->randomNumber(),
            'resident_type' => $this->faker->word(),
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now(),
            'change_reason' => $this->faker->word(),
            'notes' => $this->faker->word(),
            'created_by' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
