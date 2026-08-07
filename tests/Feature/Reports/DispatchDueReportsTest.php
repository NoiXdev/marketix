<?php

namespace Tests\Feature\Reports;

use App\Jobs\SendScheduledReport;
use App\Models\Project;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDueReportsTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(Project $project, User $user, array $overrides = []): ScheduledReport
    {
        return ScheduledReport::create(array_merge([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'name' => 'Weekly overview',
            'type' => 'project_summary',
            'subject_id' => null,
            'frequency' => 'weekly',
            'weekday' => 1,
            'period' => 'last_7_days',
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => ['a@example.test'],
            'active' => true,
            'next_run_at' => now()->subHour(),
        ], $overrides));
    }

    public function test_active_report_with_past_next_run_at_is_dispatched_and_rescheduled(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $report = $this->makeReport($project, $user, [
            'next_run_at' => now()->subHour(),
        ]);

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertPushed(SendScheduledReport::class, 1);
        Queue::assertPushed(SendScheduledReport::class, function (SendScheduledReport $job) use ($report) {
            return $job->report->is($report);
        });

        $report->refresh();

        $this->assertNotNull($report->last_sent_at);
        $this->assertTrue($report->next_run_at->isFuture());
    }

    public function test_inactive_report_with_past_next_run_at_is_not_dispatched(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $this->makeReport($project, $user, [
            'active' => false,
            'next_run_at' => now()->subHour(),
        ]);

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertNotPushed(SendScheduledReport::class);
    }

    public function test_active_report_with_future_next_run_at_is_not_dispatched(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $this->makeReport($project, $user, [
            'active' => true,
            'next_run_at' => now()->addDay(),
        ]);

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertNotPushed(SendScheduledReport::class);
    }

    public function test_two_due_reports_are_both_dispatched(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $this->makeReport($project, $user, ['name' => 'Report A', 'next_run_at' => now()->subHour()]);
        $this->makeReport($project, $user, ['name' => 'Report B', 'next_run_at' => now()->subMinutes(30)]);

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertPushed(SendScheduledReport::class, 2);
    }

    public function test_three_due_reports_are_all_dispatched_and_advanced(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $reports = [
            $this->makeReport($project, $user, ['name' => 'Report A', 'next_run_at' => now()->subHours(3)]),
            $this->makeReport($project, $user, ['name' => 'Report B', 'next_run_at' => now()->subHours(2)]),
            $this->makeReport($project, $user, ['name' => 'Report C', 'next_run_at' => now()->subHour()]),
        ];

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertPushed(SendScheduledReport::class, 3);

        foreach ($reports as $report) {
            $report->refresh();
            $this->assertNotNull($report->last_sent_at);
            $this->assertTrue($report->next_run_at->isFuture());
        }
    }

    public function test_active_report_with_null_next_run_at_is_not_dispatched(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $this->makeReport($project, $user, [
            'active' => true,
            'next_run_at' => null,
        ]);

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertNotPushed(SendScheduledReport::class);
    }

    public function test_active_due_report_with_soft_deleted_project_is_not_dispatched(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $user = User::factory()->create();
        $this->makeReport($project, $user, [
            'active' => true,
            'next_run_at' => now()->subHour(),
        ]);

        $project->delete();

        $this->artisan('reports:dispatch-due')->assertExitCode(0);

        Queue::assertNotPushed(SendScheduledReport::class);
    }
}
