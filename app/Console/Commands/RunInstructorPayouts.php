<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\InstructorEarning;
use Illuminate\Console\Command;

/**
 * php artisan payouts:run [--period=2026-09]
 *
 * Deliberately dumb at this level: it just finds every instructor with at
 * least one unclaimed earning and dispatches one job per instructor. All the
 * actual safety (no double pay on re-run, no double pay on retry) lives in
 * the job + service, not here - so this command can be triggered by a
 * scheduler, a manual re-run, or two servers simultaneously without risk.
 *
 * Uses chunkById over a distinct instructor_id query to stay memory-safe
 * against a ledger with tens of millions of rows.
 */
class RunInstructorPayouts extends Command
{
    protected $signature = 'payouts:run {--period=}';

    protected $description = 'Dispatch a payout job for every instructor with outstanding (unclaimed) earnings';

    public function handle(): int
    {
        $periodKey = $this->option('period') ?? now()->format('Y-m');
        $dispatched = 0;

        InstructorEarning::whereNull('payout_id')
            ->select('instructor_id')
            ->distinct()
            ->orderBy('instructor_id')
            ->chunk(500, function ($rows) use ($periodKey, &$dispatched) {
                foreach ($rows as $row) {
                    ProcessInstructorPayoutJob::dispatch($row->instructor_id, $periodKey);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} payout job(s) for period {$periodKey}.");

        return self::SUCCESS;
    }
}
