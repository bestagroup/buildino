<?php

namespace Database\Factories;

use App\Models\BuildingDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BuildingDocumentFactory extends Factory
{
    protected $model = BuildingDocument::class;

    public function definition(): array
    {
        return [
            'building_id' => $this->faker->randomNumber(),
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
