<?php

namespace App\Actions;

use App\Enums\EarningType;
use App\Enums\SubscriptionStatus;
use App\Models\InstructorEarning;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Handles a mid-term refund.
 *
 * Decision: refund the unused portion of the term on a straight-line, elapsed
 * calendar time basis (not "lessons watched" or similar usage metric - we
 * have no such signal here, and time-based proration is what most subscription
 * businesses actually do). The same ratio is applied in reverse to each
 * instructor's earning for that subscription, as a NEW negative ledger row -
 * we never edit or delete the original earning, so the history of "what was
 * allocated and when" stays intact and auditable.
 *
 * Consequence we accept deliberately: if the instructor's original earning
 * was already paid out, this reversal makes their running balance negative.
 * We do not attempt to claw back the specific payout. Instead the negative
 * balance is netted against the instructor's future earnings the next time
 * a payout runs (claimOutstandingEarnings sums all unclaimed rows, positive
 * and negative, before paying). This avoids reversing a payment that may
 * already have left the platform's account, at the cost of an instructor
 * occasionally seeing a $0 payout for a period while a prior refund is
 * absorbed. This trade-off is called out again in docs/ARCHITECTURE.md.
 */
class ProcessRefundAction
{
    public function execute(Subscription $subscription, ?\DateTimeInterface $asOf = null): void
    {
        $asOf = $asOf ?? now();

        DB::transaction(function () use ($subscription, $asOf) {
            $fresh = Subscription::whereKey($subscription->id)->lockForUpdate()->first();

            if ($fresh->status === SubscriptionStatus::Refunded) {
                return; // idempotent: already refunded, no-op
            }

            $totalSeconds = max(1, $fresh->starts_at->diffInSeconds($fresh->ends_at));
            $elapsedSeconds = min($totalSeconds, max(0, $fresh->starts_at->diffInSeconds($asOf)));
            $unusedRatio = ($totalSeconds - $elapsedSeconds) / $totalSeconds;

            $refundCents = (int) round($fresh->amount_cents * $unusedRatio);

            $earnings = InstructorEarning::where('subscription_id', $fresh->id)
                ->where('type', EarningType::Earning)
                ->lockForUpdate()
                ->get();

            $reversalRows = [];
            foreach ($earnings as $earning) {
                $reversal = (int) round($earning->amount_cents * $unusedRatio);
                if ($reversal <= 0) {
                    continue;
                }
                $reversalRows[] = [
                    'subscription_id' => $fresh->id,
                    'instructor_id' => $earning->instructor_id,
                    'amount_cents' => -$reversal,
                    'type' => EarningType::RefundAdjustment->value,
                    'payout_id' => null,
                    'earned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($reversalRows)) {
                InstructorEarning::insert($reversalRows);
            }

            $fresh->update([
                'status' => SubscriptionStatus::Refunded,
                'refunded_at' => now(),
                'refunded_amount_cents' => $refundCents,
            ]);
        });
    }
}
