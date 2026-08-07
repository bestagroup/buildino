<?php

namespace Database\Factories;

use App\Models\Complex;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ComplexFactory extends Factory
{
    protected $model = Complex::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->word(),
            'title' => $this->faker->word(),
            'manager_name' => $this->faker->name(),
            'manager_mobile' => $this->faker->word(),
            'province' => $this->faker->word(),
            'city' => $this->faker->city(),
            'address' => $this->faker->address(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'sort_order' => $this->faker->randomNumber(),
            'is_active' => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
