<?php

use App\Actions\AllocateSubscriptionRevenueAction;
use App\Models\Course;
use App\Models\InstructorEarning;
use App\Models\Subscription;
use App\Models\SubscriptionCourseAccess;
use App\Models\SubscriptionPlan;
use App\Models\User;

function makeSubscriptionWithInstructors(int $amountCents, int $instructorCount): Subscription
{
    $plan = SubscriptionPlan::factory()->create(['price_cents' => $amountCents, 'duration_days' => 30]);
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => $amountCents,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $instructors = User::factory()->instructor()->count($instructorCount)->create();
    foreach ($instructors as $instructor) {
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        SubscriptionCourseAccess::create([
            'subscription_id' => $subscription->id,
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    return $subscription;
}

it('splits the instructor pool according to the configured platform cut', function () {
    config(['revenue.platform_cut_percent' => 30]);
    $subscription = makeSubscriptionWithInstructors(10000, 2); // $100.00, 2 instructors

    (new AllocateSubscriptionRevenueAction())->execute($subscription);

    $earnings = InstructorEarning::where('subscription_id', $subscription->id)->get();

    expect($earnings)->toHaveCount(2);
    // Pool = 10000 * 70% = 7000 cents, split evenly two ways = 3500 each.
    expect($earnings->sum('amount_cents'))->toBe(7000);
    expect($earnings->pluck('amount_cents')->unique()->values()->all())->toBe([3500]);
});

it('distributes remainder cents deterministically so the split always sums exactly', function () {
    config(['revenue.platform_cut_percent' => 30]);
    // Pool = 10000 * 70% = 7000, split 3 ways = 2333.33... per instructor.
    $subscription = makeSubscriptionWithInstructors(10000, 3);

    (new AllocateSubscriptionRevenueAction())->execute($subscription);

    $earnings = InstructorEarning::where('subscription_id', $subscription->id)
        ->orderBy('instructor_id')
        ->pluck('amount_cents');

    expect($earnings->sum())->toBe(7000);
    // floor(7000/3) = 2333, remainder 1 cent goes to the first instructor.
    expect($earnings->first())->toBe(2334);
    expect($earnings->skip(1)->unique()->values()->all())->toBe([2333]);
});

it('never allocates the same subscription twice, even if the action runs twice', function () {
    $subscription = makeSubscriptionWithInstructors(10000, 3);
    $action = new AllocateSubscriptionRevenueAction();

    $action->execute($subscription);
    $action->execute($subscription); // simulate the action being triggered again

    expect(InstructorEarning::where('subscription_id', $subscription->id)->count())->toBe(3);
});

it('does nothing when a subscription has no course access rows', function () {
    $plan = SubscriptionPlan::factory()->create();
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => $plan->price_cents,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    (new AllocateSubscriptionRevenueAction())->execute($subscription);

    expect(InstructorEarning::where('subscription_id', $subscription->id)->count())->toBe(0);
});
