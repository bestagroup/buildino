<?php

namespace Database\Factories;

use App\Models\BuildingRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BuildingRuleFactory extends Factory
{
    protected $model = BuildingRule::class;

    public function definition(): array
    {
        return [
            'building_id' => $this->faker->randomNumber(),
            'title' => $this->faker->word(),
            'content' => $this->faker->word(),
            'is_active' => $this->faker->boolean(),
            'effective_from' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
