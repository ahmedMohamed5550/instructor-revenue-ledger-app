<?php

namespace App\Actions;

use App\Enums\EarningType;
use App\Models\InstructorEarning;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Turns a subscription payment into per-instructor ledger entries.
 *
 * Decisions made (see docs/ARCHITECTURE.md for the fuller reasoning):
 *  - Revenue is recognised in full on day one, not spread across the term.
 *    A subscription's instructor list is fixed via a snapshot, so "who gets
 *    paid for this money" is unambiguous even though access is delivered
 *    over months. Recognising ratably would be more "correct" accounting-wise
 *    but adds real complexity (a scheduled job per subscription per day) for
 *    a take-home scope; the trade-off is written down rather than hidden.
 *  - Platform cut is a flat percentage, configurable in config/revenue.php.
 *  - The instructor pool doesn't split evenly in cents. We floor-divide, then
 *    give the leftover cents (never more than count-1 cents) to instructors
 *    in ascending instructor_id order. This is simple and 100% auditable,
 *    but does mean the lowest instructor_id in a course bundle very slightly
 *    benefits over many subscriptions. An alternative (rotating the start
 *    index per subscription, or largest-remainder-first) would average this
 *    out over time at the cost of being harder to explain to an instructor
 *    who asks "why did I get 1 cent more/less than my co-instructor".
 */
class AllocateSubscriptionRevenueAction
{
    public function execute(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $alreadyAllocated = InstructorEarning::where('subscription_id', $subscription->id)
                ->where('type', EarningType::Earning)
                ->lockForUpdate()
                ->exists(); 

            if ($alreadyAllocated) {
                return;
            }

            $instructorIds = $subscription->courseAccess()
                ->distinct()
                ->orderBy('instructor_id')
                ->pluck('instructor_id');

            if ($instructorIds->isEmpty()) {
                return;
            }

            $platformCutPercent = (float) config('revenue.platform_cut_percent', 30);
            $instructorPoolCents = (int) round($subscription->amount_cents * (100 - $platformCutPercent) / 100);

            $count = $instructorIds->count();
            $baseShare = intdiv($instructorPoolCents, $count);
            $remainderCents = $instructorPoolCents - ($baseShare * $count);

            $rows = $instructorIds->values()->map(function ($instructorId, $index) use (
                $subscription, $baseShare, $remainderCents
            ) {
                $amount = $baseShare + ($index < $remainderCents ? 1 : 0);

                return [
                    'subscription_id' => $subscription->id,
                    'instructor_id' => $instructorId,
                    'amount_cents' => $amount,
                    'type' => EarningType::Earning->value,
                    'payout_id' => null,
                    'earned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            InstructorEarning::insert($rows->all());
        });
    }
}
