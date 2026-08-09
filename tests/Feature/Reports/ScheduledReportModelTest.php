<?php

namespace Tests\Feature\Reports;

use App\Models\Project;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledReportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_casts_relations_and_attributes(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $report = ScheduledReport::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'name' => 'Weekly overview',
            'type' => 'project',
            'frequency' => 'weekly',
            'weekday' => 1,
            'period' => 'last_7_days',
            'formats' => ['pdf', 'csv'],
            'recipients' => ['a@example.com', 'b@example.com'],
            'active' => true,
            'next_run_at' => now()->addDay(),
        ]);

        $this->assertIsArray($report->formats);
        $this->assertSame(['pdf', 'csv'], $report->formats);
        $this->assertIsArray($report->recipients);
        $this->assertSame(['a@example.com', 'b@example.com'], $report->recipients);
        $this->assertIsBool($report->active);
        $this->assertTrue($report->active);
        $this->assertInstanceOf(Carbon::class, $report->next_run_at);

        $this->assertTrue($report->project->is($project));
        $this->assertTrue($report->creator->is($user));
    }

    public function test_deleting_project_cascade_deletes_scheduled_reports(): void
    {
        $project = Project::factory()->create();

        $report = ScheduledReport::create([
            'project_id' => $project->id,
            'name' => 'Monthly summary',
            'type' => 'project',
            'frequency' => 'monthly',
            'day_of_month' => 1,
            'period' => 'last_30_days',
            'formats' => ['pdf'],
            'recipients' => ['a@example.com'],
        ]);

        // Delete the row directly via the DB facade (bypasses Eloquent's SoftDeletes
        // scope and activity-logging-on-delete behavior) so we exercise the FK's
        // cascadeOnDelete directly.
        DB::table('projects')->where('id', $project->id)->delete();

        $this->assertDatabaseMissing('scheduled_reports', ['id' => $report->id]);
    }
}
