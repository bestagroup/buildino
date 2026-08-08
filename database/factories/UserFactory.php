<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),

            'national_code' => fake()->unique()->numerify('##########'),
            'mobile' => fake()->unique()->numerify('09#########'),

            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),

            'password' => Hash::make('password'),

            'is_active' => true,
            'is_blocked' => false,

            'remember_token' => Str::random(10),
        ];
    }
}
