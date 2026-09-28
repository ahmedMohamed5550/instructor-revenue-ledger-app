<?php

use App\Enums\PayoutStatus;
use App\Models\InstructorEarning;
use App\Models\InstructorPayout;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

it('reports an instructor balance reflecting earned, paid, and outstanding amounts', function () {
    $instructor = User::factory()->instructor()->create();
    $plan = SubscriptionPlan::factory()->create();
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => 5000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $paidPayout = InstructorPayout::create([
        'instructor_id' => $instructor->id,
        'period_key' => '2026-08',
        'amount_cents' => 2000,
        'status' => PayoutStatus::Succeeded->value,
        'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    InstructorEarning::create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_cents' => 2000,
        'type' => 'earning',
        'payout_id' => $paidPayout->id,
        'earned_at' => now(),
    ]);
    InstructorEarning::create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_cents' => 3000,
        'type' => 'earning',
        'payout_id' => null,
        'earned_at' => now(),
    ]);

    $response = $this->getJson("/api/instructors/{$instructor->id}/balance");

    $response->assertOk();
    $response->assertJson([
        'total_earned_cents' => 5000,
        'total_paid_cents' => 2000,
        'outstanding_cents' => 3000,
    ]);
});

it('lists payout history for an instructor', function () {
    $instructor = User::factory()->instructor()->create();
    InstructorPayout::create([
        'instructor_id' => $instructor->id,
        'period_key' => '2026-08',
        'amount_cents' => 1000,
        'status' => PayoutStatus::Succeeded->value,
        'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response = $this->getJson("/api/instructors/{$instructor->id}/payouts");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.status', 'succeeded');
});
