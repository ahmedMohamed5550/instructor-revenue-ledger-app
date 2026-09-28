<?php

use App\Contracts\PaymentProviderContract;
use App\Enums\PayoutStatus;
use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\Course;
use App\Models\InstructorEarning;
use App\Models\InstructorPayout;
use App\Models\Subscription;
use App\Models\SubscriptionCourseAccess;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\InstructorPayoutService;
use Tests\Fakes\AlwaysSucceedsProvider;

function seedOutstandingEarning(User $instructor, int $amountCents = 5000): void
{
    $plan = SubscriptionPlan::factory()->create();
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => $amountCents,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    InstructorEarning::create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_cents' => $amountCents,
        'type' => 'earning',
        'payout_id' => null,
        'earned_at' => now(),
    ]);
}

it('running the payout process twice for the same period never double-pays', function () {
    $provider = new AlwaysSucceedsProvider();
    $this->app->instance(PaymentProviderContract::class, $provider);

    $instructor = User::factory()->instructor()->create();
    seedOutstandingEarning($instructor, 5000);

    $period = '2026-09';

    // Run the job "twice" - simulating the artisan command firing on an
    // overlapping schedule, or being manually re-triggered.
    (new ProcessInstructorPayoutJob($instructor->id, $period))->handle(app(InstructorPayoutService::class));
    (new ProcessInstructorPayoutJob($instructor->id, $period))->handle(app(InstructorPayoutService::class));

    expect(InstructorPayout::where('instructor_id', $instructor->id)->count())->toBe(1);
    expect(InstructorPayout::sole()->amount_cents)->toBe(5000);
    expect(InstructorPayout::sole()->status)->toBe(PayoutStatus::Succeeded);
    expect($provider->charges)->toHaveCount(1); // provider was only ever called once
});

it('a retried job (e.g. after a worker crash) never double-pays', function () {
    $provider = new AlwaysSucceedsProvider();
    $this->app->instance(PaymentProviderContract::class, $provider);

    $instructor = User::factory()->instructor()->create();
    seedOutstandingEarning($instructor, 7500);

    $job = new ProcessInstructorPayoutJob($instructor->id, '2026-09');

    // Simulate the queue retrying the exact same job instance after a crash.
    $job->handle(app(InstructorPayoutService::class));
    $job->handle(app(InstructorPayoutService::class));
    $job->handle(app(InstructorPayoutService::class));

    expect(InstructorPayout::count())->toBe(1);
    expect(InstructorPayout::sole()->amount_cents)->toBe(7500);
});

it('claims earnings so a new subscription payment after a payout starts a fresh, separate balance', function () {
    $provider = new AlwaysSucceedsProvider();
    $this->app->instance(PaymentProviderContract::class, $provider);

    $instructor = User::factory()->instructor()->create();
    seedOutstandingEarning($instructor, 1000);

    (new ProcessInstructorPayoutJob($instructor->id, '2026-09'))->handle(app(InstructorPayoutService::class));

    // A second, later earning should NOT be swept into the already-paid payout.
    seedOutstandingEarning($instructor, 2000);

    expect(InstructorEarning::where('instructor_id', $instructor->id)->whereNull('payout_id')->sum('amount_cents'))
        ->toBe(2000);
    expect(InstructorPayout::sole()->amount_cents)->toBe(1000);
});
