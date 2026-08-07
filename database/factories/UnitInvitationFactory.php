<?php

namespace Database\Factories;

use App\Models\UnitInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UnitInvitationFactory extends Factory
{
    protected $model = UnitInvitation::class;

    public function definition(): array
    {
        return [
            'unit_id' => $this->faker->randomNumber(),
            'invited_by' => $this->faker->randomNumber(),
            'mobile' => $this->faker->word(),
            'email' => $this->faker->unique()->safeEmail(),
            'resident_type' => $this->faker->word(),
            'token' => Str::random(10),
            'status' => $this->faker->word(),
            'expires_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
            'accepted_user_id' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
