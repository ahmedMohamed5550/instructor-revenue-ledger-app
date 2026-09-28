<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstructorPayoutResource;
use App\Models\User;
use App\Services\InstructorLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only endpoints answering exactly the three questions the brief asks
 * the system to be able to answer at any time: how much an instructor is
 * owed, how much has been paid, how much is outstanding - plus the payout
 * history behind those numbers.
 *
 * Note: there is deliberately no "trigger a payout" endpoint here. Moving
 * real money is only ever initiated via `php artisan payouts:run`
 * (scheduled or manually invoked by an operator), never by an arbitrary
 * HTTP request - that keeps every payout trigger in one auditable place.
 */
class InstructorLedgerController extends Controller
{
    public function __construct(private InstructorLedgerService $ledger) {}

    public function balance(User $instructor): JsonResponse
    {
        return response()->json($this->ledger->balance($instructor));
    }

    public function payouts(User $instructor): AnonymousResourceCollection
    {
        return InstructorPayoutResource::collection(
            $this->ledger->payoutHistory($instructor)
        );
    }
}
