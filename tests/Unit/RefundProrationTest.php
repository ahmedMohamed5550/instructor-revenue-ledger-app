<?php

use App\Actions\AllocateSubscriptionRevenueAction;
use App\Actions\ProcessRefundAction;
use App\Enums\EarningType;
use App\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\InstructorEarning;
use App\Models\Subscription;
use App\Models\SubscriptionCourseAccess;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;

it('reverses roughly the unused proportion of each instructor earning on a mid-term refund', function () {
    config(['revenue.platform_cut_percent' => 0]); // simplify: instructors get 100% of pool

    $start = Carbon::parse('2026-01-01 00:00:00');
    $plan = SubscriptionPlan::factory()->create(['price_cents' => 3000, 'duration_days' => 30]);
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => 3000,
        'starts_at' => $start,
        'ends_at' => $start->copy()->addDays(30),
        'status' => 'active',
    ]);

    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);
    SubscriptionCourseAccess::create([
        'subscription_id' => $subscription->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);

    (new AllocateSubscriptionRevenueAction())->execute($subscription);

    // Refund exactly 10 days into a 30-day term -> 2/3 of the term unused.
    $refundAt = $start->copy()->addDays(10);
    (new ProcessRefundAction())->execute($subscription->fresh(), $refundAt);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Refunded);

    $earning = InstructorEarning::where('type', EarningType::Earning)->sole();
    $adjustment = InstructorEarning::where('type', EarningType::RefundAdjustment)->sole();

    // Original earning was 3000 cents; ~2/3 (2000 cents) should be reversed.
    expect($adjustment->amount_cents)->toBe(-2000);
    expect($earning->amount_cents + $adjustment->amount_cents)->toBe(1000); // instructor keeps ~1/3
});

it('is idempotent: refunding a subscription twice does not double-reverse earnings', function () {
    config(['revenue.platform_cut_percent' => 0]);

    $plan = SubscriptionPlan::factory()->create(['price_cents' => 3000, 'duration_days' => 30]);
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => 3000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);
    SubscriptionCourseAccess::create([
        'subscription_id' => $subscription->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);

    (new AllocateSubscriptionRevenueAction())->execute($subscription);

    $action = new ProcessRefundAction();
    $action->execute($subscription->fresh());
    $action->execute($subscription->fresh()); // second call, e.g. a retried webhook

    expect(InstructorEarning::where('type', EarningType::RefundAdjustment)->count())->toBe(1);
});
