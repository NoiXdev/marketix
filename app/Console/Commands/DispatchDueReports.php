<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledReport;
use App\Models\ScheduledReport;
use App\Reports\NextRunCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Hourly-scheduled entry point for scheduled reporting. Dispatches
 * SendScheduledReport for every active report whose next_run_at is due,
 * then advances its own scheduling bookkeeping (last_sent_at/next_run_at)
 * so the next invocation doesn't re-dispatch the same run.
 *
 * A report with a NULL next_run_at is treated as not-due: the
 * `<= now()` comparison excludes NULL in SQL. Reports get their first
 * next_run_at set on create (Task 8).
 */
class DispatchDueReports extends Command
{
    protected $signature = 'reports:dispatch-due';

    protected $description = 'Dispatch SendScheduledReport for every active scheduled report that is due, then advance its schedule';

    public function handle(): int
    {
        $sendHour = config('reports.send_hour');

        // Load the due set once (a single WHERE evaluation) rather than
        // chunking: chunk() re-runs the same WHERE per page with an
        // offset, but the callback below mutates next_run_at, so
        // processed rows drop out of the WHERE and later pages would
        // overshoot and skip still-due rows. Due-per-hour is bounded, so
        // ->get() is appropriate here.
        $due = ScheduledReport::query()
            ->where('active', true)
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $report) {
            SendScheduledReport::dispatch($report);

            $report->forceFill([
                'last_sent_at' => now(),
                'next_run_at' => NextRunCalculator::next(
                    $report->frequency,
                    $report->weekday,
                    $report->day_of_month,
                    $sendHour,
                    CarbonImmutable::now(),
                ),
            ])->save();
        }

        $dispatched = $due->count();
        $this->info("Dispatched {$dispatched} due scheduled report(s).");

        return self::SUCCESS;
    }
}
