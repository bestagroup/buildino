<?php

namespace Database\Factories;

use App\Models\UnitDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UnitDocumentFactory extends Factory
{
    protected $model = UnitDocument::class;

    public function definition(): array
    {
        return [
            'unit_id' => $this->faker->randomNumber(),
            'title' => $this->faker->word(),
            'type' => $this->faker->word(),
            'file_name' => $this->faker->name(),
            'file_path' => $this->faker->word(),
            'mime_type' => $this->faker->word(),
            'file_size' => $this->faker->randomNumber(),
            'uploaded_by' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
