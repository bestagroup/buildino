<?php

namespace Database\Factories;

use App\Models\StorageUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class StorageUnitFactory extends Factory
{
    protected $model = StorageUnit::class;

    public function definition(): array
    {
        return [
            'unit_id' => $this->faker->randomNumber(),
            'storage_number' => $this->faker->word(),
            'area' => $this->faker->randomFloat(),
            'is_active' => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
