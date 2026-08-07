<?php

namespace App\Jobs;

use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReport;
use App\Reports\PeriodResolver;
use App\Reports\ReportDateRange;
use App\Reports\ReportTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Renders and sends a single scheduled report run. Dispatched by the
 * Task-7/8 scheduling command for due reports, or directly for "send now".
 *
 * Deliberately does not touch ScheduledReport::last_sent_at/next_run_at —
 * the dispatching command owns scheduling bookkeeping, so a manual
 * "send now" (which passes $overrideRecipients) must not shift the
 * regular schedule.
 */
class SendScheduledReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ScheduledReport $report,
        public ?array $overrideRecipients = null,
    ) {}

    public function handle(ReportTypeRegistry $registry): void
    {
        $type = $registry->for($this->report->type);

        if (! $type->validateSubject($this->report->project, $this->report->subject_id)) {
            Log::warning('Skipping scheduled report: subject no longer valid.', [
                'scheduled_report_id' => $this->report->id,
                'type' => $this->report->type,
                'subject_id' => $this->report->subject_id,
            ]);

            return;
        }

        [$start, $end] = PeriodResolver::resolve($this->report->period, CarbonImmutable::now());
        $range = ReportDateRange::custom($start, $end);

        $viewData = $type->viewData($this->report->project, $this->report->subject_id, $range);

        $csv = ($this->report->formats['csv'] ?? false) ? $type->csv($viewData) : null;

        $pdf = ($this->report->formats['pdf'] ?? false)
            ? base64_decode(Pdf::view($type->pdfView(), $viewData)->format('a4')->base64())
            : null;

        $recipients = $this->overrideRecipients ?? $this->report->recipients;

        if (empty($recipients)) {
            return;
        }

        $subject = trans('reports.email.subject', [
            'name' => $this->report->name,
            'period' => $range->label(),
        ]);

        Mail::to($recipients)->send(new ScheduledReportMail($type->emailView(), $viewData, $subject, $csv, $pdf));
    }
}
