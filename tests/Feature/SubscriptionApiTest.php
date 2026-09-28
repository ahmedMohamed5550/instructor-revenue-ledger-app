<?php

use App\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\InstructorEarning;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

it('creates a subscription via the API and allocates instructor earnings', function () {
    config(['revenue.platform_cut_percent' => 30]);

    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->monthly()->create(['price_cents' => 10000]);
    $instructorA = User::factory()->instructor()->create();
    $instructorB = User::factory()->instructor()->create();
    $courseA = Course::factory()->create(['instructor_id' => $instructorA->id]);
    $courseB = Course::factory()->create(['instructor_id' => $instructorB->id]);

    $response = $this->postJson('/api/subscriptions', [
        'student_id' => $student->id,
        'plan_id' => $plan->id,
        'course_ids' => [$courseA->id, $courseB->id],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'active');
    $response->assertJsonPath('data.amount_cents', 10000);

    $subscriptionId = $response->json('data.id');
    $earnings = InstructorEarning::where('subscription_id', $subscriptionId)->get();

    expect($earnings)->toHaveCount(2);
    expect($earnings->sum('amount_cents'))->toBe(7000); // 70% pool split across 2 instructors
});

it('rejects a subscription request with a non-existent course id', function () {
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->monthly()->create();

    $response = $this->postJson('/api/subscriptions', [
        'student_id' => $student->id,
        'plan_id' => $plan->id,
        'course_ids' => [999999],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['course_ids.0']);
});

it('refunds a subscription via the API and is idempotent when called twice', function () {
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->monthly()->create(['price_cents' => 10000, 'duration_days' => 30]);
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    $subscription = Subscription::create([
        'student_id' => $student->id,
        'plan_id' => $plan->id,
        'amount_cents' => 10000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);
    \App\Models\SubscriptionCourseAccess::create([
        'subscription_id' => $subscription->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);
    app(\App\Actions\AllocateSubscriptionRevenueAction::class)->execute($subscription);

    $first = $this->postJson("/api/subscriptions/{$subscription->id}/refund");
    $first->assertOk();
    $first->assertJsonPath('data.status', 'refunded');

    $second = $this->postJson("/api/subscriptions/{$subscription->id}/refund");
    $second->assertOk();
    $second->assertJsonPath('data.status', 'refunded');

    // Only one reversal row exists despite two refund calls.
    expect(InstructorEarning::where('type', 'refund_adjustment')->count())->toBe(1);
});
