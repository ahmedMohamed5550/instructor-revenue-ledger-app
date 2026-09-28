<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $plan = SubscriptionPlan::factory()->monthly()->create();
        $start = now();

        return [
            'student_id' => User::factory()->student(),
            'plan_id' => $plan->id,
            'amount_cents' => $plan->price_cents,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($plan->duration_days),
            'status' => 'active',
        ];
    }
}
