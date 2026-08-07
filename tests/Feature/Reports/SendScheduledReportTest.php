<?php

namespace Tests\Feature\Reports;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\SendScheduledReport;
use App\Mail\ScheduledReportMail;
use App\Models\Domain;
use App\Models\Project;
use App\Models\ScheduledReport;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PDF-in-tests note: rendering a real PDF goes through spatie/laravel-pdf's
 * Browsershot driver, which needs headless Chrome — not guaranteed to be
 * available in the test environment. To keep this suite hermetic, every
 * test here uses formats.pdf=false and exercises the CSV + no-attachment +
 * recipients + subject-deleted paths, which cover 100% of
 * SendScheduledReport's branching without touching the Pdf facade. The
 * `->format('a4')->base64()` PDF-rendering line itself is intentionally not
 * unit-tested here (it is a one-line, effectively static call into a
 * third-party facade); ReportDownloadTest already demonstrates the
 * project's established pattern for stubbing that facade with `Pdf::fake()`
 * for the on-demand download controller.
 */
class SendScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeProjectReport(Project $project, array $overrides = []): ScheduledReport
    {
        return ScheduledReport::create(array_merge([
            'project_id' => $project->id,
            'name' => 'Weekly overview',
            'type' => 'project_summary',
            'subject_id' => null,
            'frequency' => 'weekly',
            'weekday' => 1,
            'period' => 'last_7_days',
            'formats' => ['csv' => false, 'pdf' => false],
            'recipients' => ['a@example.test', 'b@example.test'],
            'active' => true,
        ], $overrides));
    }

    private function createUrl(Project $project, string $slug): Url
    {
        $user = User::factory()->create();
        $domain = Domain::create([
            'project_id' => $project->id,
            'name' => "{$slug}.test",
        ]);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => "https://{$slug}.example.test",
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ]);
    }

    public function test_csv_only_report_is_sent_to_all_recipients_with_one_attachment(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $report = $this->makeProjectReport($project, [
            'formats' => ['csv' => true, 'pdf' => false],
        ]);

        SendScheduledReport::dispatchSync($report);

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) {
            return $mail->hasTo('a@example.test')
                && $mail->hasTo('b@example.test')
                && count($mail->attachments()) === 1
                && $mail->csv !== null
                && $mail->pdf === null;
        });
    }

    public function test_report_with_no_formats_is_sent_with_zero_attachments(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $report = $this->makeProjectReport($project, [
            'formats' => ['csv' => false, 'pdf' => false],
        ]);

        SendScheduledReport::dispatchSync($report);

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) {
            return count($mail->attachments()) === 0
                && $mail->csv === null
                && $mail->pdf === null;
        });
    }

    public function test_deleted_subject_causes_nothing_to_be_sent(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $url = $this->createUrl($project, 'go');

        $report = ScheduledReport::create([
            'project_id' => $project->id,
            'name' => 'Link report',
            'type' => 'link',
            'subject_id' => $url->id,
            'frequency' => 'weekly',
            'weekday' => 1,
            'period' => 'last_7_days',
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => ['a@example.test'],
            'active' => true,
        ]);

        $url->delete();

        SendScheduledReport::dispatchSync($report);

        Mail::assertNothingSent();
    }

    public function test_override_recipients_sends_only_to_the_override(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $report = $this->makeProjectReport($project, [
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => ['a@example.test', 'b@example.test'],
        ]);

        SendScheduledReport::dispatchSync($report, ['me@x.io']);

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) {
            return $mail->hasTo('me@x.io')
                && ! $mail->hasTo('a@example.test')
                && ! $mail->hasTo('b@example.test');
        });
    }

    public function test_send_does_not_touch_scheduling_bookkeeping(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $report = $this->makeProjectReport($project, [
            'formats' => ['csv' => true, 'pdf' => false],
            'next_run_at' => now()->addDay(),
        ]);
        $originalNextRunAt = $report->next_run_at;

        SendScheduledReport::dispatchSync($report, ['override@example.test']);

        $report->refresh();

        $this->assertNull($report->last_sent_at);
        $this->assertEquals($originalNextRunAt, $report->next_run_at);
    }

    public function test_empty_recipients_and_no_override_sends_nothing(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $report = $this->makeProjectReport($project, [
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => [],
        ]);

        SendScheduledReport::dispatchSync($report);

        Mail::assertNothingSent();
    }

    public function test_soft_deleted_project_causes_nothing_to_be_sent(): void
    {
        Mail::fake();

        $project = Project::factory()->create();
        $report = $this->makeProjectReport($project, [
            'formats' => ['csv' => true, 'pdf' => false],
        ]);

        $project->delete();

        SendScheduledReport::dispatchSync($report);

        Mail::assertNothingSent();
    }
}
