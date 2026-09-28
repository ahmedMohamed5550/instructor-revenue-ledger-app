<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['monthly', 'quarterly', 'annual']),
            'name' => fake()->word(),
            'price_cents' => 10000,
            'duration_days' => 30,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['code' => 'monthly', 'name' => 'Monthly', 'price_cents' => 10000, 'duration_days' => 30]);
    }

    public function quarterly(): static
    {
        return $this->state(fn () => ['code' => 'quarterly', 'name' => 'Quarterly', 'price_cents' => 27000, 'duration_days' => 90]);
    }

    public function annual(): static
    {
        return $this->state(fn () => ['code' => 'annual', 'name' => 'Annual', 'price_cents' => 96000, 'duration_days' => 365]);
    }
}
