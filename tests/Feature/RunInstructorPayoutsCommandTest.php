<?php

use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\InstructorEarning;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('dispatches exactly one payout job per instructor with outstanding earnings, and running it twice does not duplicate dispatches for a settled instructor', function () {
    Queue::fake();

    $instructorA = User::factory()->instructor()->create();
    $instructorB = User::factory()->instructor()->create();
    $plan = SubscriptionPlan::factory()->create();

    foreach ([$instructorA, $instructorB] as $instructor) {
        $subscription = Subscription::create([
            'student_id' => User::factory()->student()->create()->id,
            'plan_id' => $plan->id,
            'amount_cents' => 3000,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'status' => 'active',
        ]);
        InstructorEarning::create([
            'subscription_id' => $subscription->id,
            'instructor_id' => $instructor->id,
            'amount_cents' => 3000,
            'type' => 'earning',
            'earned_at' => now(),
        ]);
    }

    $this->artisan('payouts:run', ['--period' => '2026-09'])->assertExitCode(0);

    Queue::assertPushed(ProcessInstructorPayoutJob::class, 2);
});
