<?php

namespace App\Services;

use App\Contracts\PaymentProviderContract;
use App\Enums\PayoutStatus;
use App\Exceptions\PaymentProviderTimeoutException;
use App\Jobs\ReconcilePayoutStatusJob;
use App\Models\InstructorEarning;
use App\Models\InstructorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The idempotency core of the whole system. Two mechanisms work together:
 *
 *  1. CLAIMING: outstanding earnings are atomically "claimed" by setting their
 *     payout_id inside a locked transaction. Once claimed, they can never be
 *     picked up by a second concurrent run - there is nothing left to claim.
 *  2. PERIOD UNIQUENESS: a DB-level unique constraint on
 *     (instructor_id, period_key) means even if two processes both try to
 *     create a payout for the same instructor+period at the same instant,
 *     only one row can exist; the loser's insert fails and is treated as
 *     "someone else already handled this".
 *
 * Together these mean running the payout command twice, retrying a job, or
 * running two queue workers concurrently cannot produce two payouts for the
 * same money.
 */
class InstructorPayoutService
{
    public function __construct(private PaymentProviderContract $provider) {}

    public function claimOutstandingEarnings(int $instructorId, string $periodKey): ?InstructorPayout
    {
        return DB::transaction(function () use ($instructorId, $periodKey) {
            $existing = InstructorPayout::where('instructor_id', $instructorId)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return null;
            }

            $earnings = InstructorEarning::where('instructor_id', $instructorId)
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            $totalCents = (int) $earnings->sum('amount_cents');

            if ($totalCents <= 0) {
                return null;
            }

            try {
                $payout = InstructorPayout::create([
                    'instructor_id' => $instructorId,
                    'period_key' => $periodKey,
                    'amount_cents' => $totalCents,
                    'status' => PayoutStatus::Processing->value,
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                return null;
            }

            InstructorEarning::whereIn('id', $earnings->pluck('id'))
                ->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }

    public function attemptPayment(InstructorPayout $payout): void
    {
        try {
            $result = $this->provider->charge($payout->idempotency_key, $payout->amount_cents);
        } catch (PaymentProviderTimeoutException) {
            $payout->update(['status' => PayoutStatus::Unknown->value]);
            ReconcilePayoutStatusJob::dispatch($payout->id)->delay(now()->addMinutes(5));
            return;
        }

        match ($result['status']) {
            'succeeded' => $payout->update([
                'status' => PayoutStatus::Succeeded->value,
                'provider_reference' => $result['reference'] ?? null,
                'confirmed_at' => now(),
            ]),
            'failed' => $this->releaseAndFail($payout),
            default => $payout->update(['status' => PayoutStatus::Unknown->value]),
        };
    }

    public function reconcile(InstructorPayout $payout): void
    {
        if ($payout->status !== PayoutStatus::Unknown) {
            return;
        }

        $status = $this->provider->checkStatus($payout->idempotency_key);

        match ($status) {
            'succeeded' => $payout->update([
                'status' => PayoutStatus::Succeeded->value,
                'confirmed_at' => now(),
            ]),
            'failed' => $this->releaseAndFail($payout),
            default => ReconcilePayoutStatusJob::dispatch($payout->id)->delay(now()->addMinutes(15)),
        };
    }

    private function releaseAndFail(InstructorPayout $payout): void
    {
        DB::transaction(function () use ($payout) {
            InstructorEarning::where('payout_id', $payout->id)->update(['payout_id' => null]);
            $payout->update(['status' => PayoutStatus::Failed->value]);
        });
    }
}
