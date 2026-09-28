<?php

namespace App\Jobs;

use App\Services\InstructorPayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBeUnique means Laravel's queue layer itself refuses to enqueue a
 * second copy of "pay this instructor for this period" while one is already
 * on the queue or running - a first line of defence before the job body's
 * own DB-level idempotency (claimOutstandingEarnings) even runs.
 */
class ProcessInstructorPayoutJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $instructorId,
        public string $periodKey,
    ) {
    }

    public function uniqueId(): string
    {
        return "payout:{$this->instructorId}:{$this->periodKey}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(InstructorPayoutService $service): void
    {
        $payout = $service->claimOutstandingEarnings($this->instructorId, $this->periodKey);

        if ($payout === null) {
            return;
        }

        $service->attemptPayment($payout);
    }
}
