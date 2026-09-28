<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instructor_id' => User::factory()->instructor(),
            'title' => fake()->sentence(3),
        ];
    }
}
