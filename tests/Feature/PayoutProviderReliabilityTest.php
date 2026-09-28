<?php

use App\Contracts\PaymentProviderContract;
use App\Enums\PayoutStatus;
use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\InstructorEarning;
use App\Models\InstructorPayout;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\InstructorPayoutService;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\AlwaysFailsProvider;
use Tests\Fakes\TimeoutThenSucceedsProvider;

it('releases the earnings for retry when the provider fails outright', function () {
    $this->app->instance(PaymentProviderContract::class, new AlwaysFailsProvider());

    $instructor = User::factory()->instructor()->create();
    $plan = SubscriptionPlan::factory()->create();
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => 4000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);
    InstructorEarning::create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_cents' => 4000,
        'type' => 'earning',
        'earned_at' => now(),
    ]);

    (new ProcessInstructorPayoutJob($instructor->id, '2026-09'))->handle(app(InstructorPayoutService::class));

    $payout = InstructorPayout::sole();
    expect($payout->status)->toBe(PayoutStatus::Failed);

    // Crucially: the underlying earning was released (payout_id nulled)
    // so the money is not lost - it will be picked up by the next run.
    expect(InstructorEarning::whereNull('payout_id')->sum('amount_cents'))->toBe(4000);
});

it('does not release earnings or mark a payout succeeded on a provider timeout, and queues reconciliation', function () {
    Queue::fake();
    $this->app->instance(PaymentProviderContract::class, new TimeoutThenSucceedsProvider());

    $instructor = User::factory()->instructor()->create();
    $plan = SubscriptionPlan::factory()->create();
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => 6000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);
    InstructorEarning::create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_cents' => 6000,
        'type' => 'earning',
        'earned_at' => now(),
    ]);

    (new ProcessInstructorPayoutJob($instructor->id, '2026-09'))->handle(app(InstructorPayoutService::class));

    $payout = InstructorPayout::sole();
    expect($payout->status)->toBe(PayoutStatus::Unknown);

    // Earnings must remain claimed - releasing them here could lead to a
    // second payout being created while the first may have already succeeded.
    expect(InstructorEarning::whereNull('payout_id')->count())->toBe(0);

    Queue::assertPushed(\App\Jobs\ReconcilePayoutStatusJob::class);
});

it('reconciliation resolves an unknown payout to succeeded without creating a second payout', function () {
    $this->app->instance(PaymentProviderContract::class, new TimeoutThenSucceedsProvider());

    $instructor = User::factory()->instructor()->create();
    $plan = SubscriptionPlan::factory()->create();
    $subscription = Subscription::create([
        'student_id' => User::factory()->student()->create()->id,
        'plan_id' => $plan->id,
        'amount_cents' => 6000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);
    InstructorEarning::create([
        'subscription_id' => $subscription->id,
        'instructor_id' => $instructor->id,
        'amount_cents' => 6000,
        'type' => 'earning',
        'earned_at' => now(),
    ]);

    $service = app(InstructorPayoutService::class);
    (new ProcessInstructorPayoutJob($instructor->id, '2026-09'))->handle($service);

    $payout = InstructorPayout::sole();
    expect($payout->status)->toBe(PayoutStatus::Unknown);

    $service->reconcile($payout->fresh());

    expect($payout->fresh()->status)->toBe(PayoutStatus::Succeeded);
    expect(InstructorPayout::count())->toBe(1); // still only one payout ever created
});
