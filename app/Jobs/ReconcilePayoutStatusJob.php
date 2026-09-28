<?php

namespace App\Jobs;

use App\Models\InstructorPayout;
use App\Services\InstructorPayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Follow-up job for a payout left in "unknown" status after a provider
 * timeout. Re-dispatches itself with backoff until the provider gives a
 * definitive answer, rather than ever guessing.
 */
class ReconcilePayoutStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(public int $payoutId) {}

    public function handle(InstructorPayoutService $service): void
    {
        $payout = InstructorPayout::find($this->payoutId);

        if (! $payout) {
            return;
        }

        $service->reconcile($payout);
    }
}
