<?php

namespace App\Services;

use App\Actions\AllocateSubscriptionRevenueAction;
use App\Actions\ProcessRefundAction;
use App\Enums\SubscriptionStatus;
use App\Models\Course;
use App\Models\Subscription;
use App\Models\SubscriptionCourseAccess;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the subscription lifecycle. This is the boundary a Controller
 * (or a console command, or a webhook handler) talks to - it owns turning
 * "a student wants plan X with access to courses [1,2,3]" into DB writes,
 * and delegates the actual money-splitting/reversal math to the Actions,
 * which stay single-purpose and independently unit-testable.
 *
 * Nothing HTTP-specific lives here (no Request objects, no response
 * shaping) so it's equally usable from an API controller, an Artisan
 * command, or a test.
 */
class SubscriptionService
{
    public function __construct(
        private AllocateSubscriptionRevenueAction $allocateRevenue,
        private ProcessRefundAction $processRefund,
    ) {
    }

    /**
     * @param  Collection<int, int>|array<int>  $courseIds
     */
    public function purchase(User $student, SubscriptionPlan $plan, Collection|array $courseIds): Subscription
    {
        $courseIds = collect($courseIds)->unique()->values();

        return DB::transaction(function () use ($student, $plan, $courseIds) {
            $courses = Course::whereIn('id', $courseIds)->get();

            if ($courses->count() !== $courseIds->count()) {
                throw ValidationException::withMessages([
                    'course_ids' => 'One or more course ids do not exist.',
                ]);
            }

            $subscription = Subscription::create([
                'student_id' => $student->id,
                'plan_id' => $plan->id,
                'amount_cents' => $plan->price_cents,
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_days),
                'status' => SubscriptionStatus::Active->value,
            ]);

            $accessRows = $courses->map(fn (Course $course) => [
                'subscription_id' => $subscription->id,
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            SubscriptionCourseAccess::insert($accessRows->all());

            $this->allocateRevenue->execute($subscription);

            return $subscription->fresh('courseAccess');
        });
    }

    public function refund(Subscription $subscription, ?\DateTimeInterface $asOf = null): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return $subscription;
        }

        $this->processRefund->execute($subscription, $asOf);

        return $subscription->fresh();
    }
}
