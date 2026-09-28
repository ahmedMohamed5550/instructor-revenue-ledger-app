<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-side queries for "what does an instructor's ledger look like".
 * Deliberately the single source of truth for these aggregates so the API
 * controller and any other consumer never drift out of sync with each
 * other - both call into this class rather than each writing their own
 * withSum() query.
 */
class InstructorLedgerService
{
    /**
     * Balance snapshot for a single instructor.
     *
     * @return array{total_earned_cents:int, total_paid_cents:int, outstanding_cents:int}
     */
    public function balance(User $instructor): array
    {
        return [
            'total_earned_cents' => (int) $instructor->earnings()->sum('amount_cents'),
            'total_paid_cents' => (int) $instructor->payouts()
                ->where('status', PayoutStatus::Succeeded->value)
                ->sum('amount_cents'),
            'outstanding_cents' => (int) $instructor->earnings()
                ->whereNull('payout_id')
                ->sum('amount_cents'),
        ];
    }

    public function payoutHistory(User $instructor, int $perPage = 15): LengthAwarePaginator
    {
        return $instructor->payouts()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
