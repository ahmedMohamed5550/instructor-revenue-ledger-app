<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Student->value,
        ];
    }

    public function instructor(): static
    {
        return $this->state(fn () => ['role' => UserRole::Instructor->value]);
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => UserRole::Student->value]);
    }
}
