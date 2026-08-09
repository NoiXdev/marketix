<?php

namespace Tests\Feature\Reports;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\SendScheduledReport;
use App\Models\Domain;
use App\Models\Project;
use App\Models\ScheduledReport;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledReportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Headers for an Inertia XHR navigation. The Reports/* React pages are
     * Task 9's deliverable and don't exist yet, so a full (non-Inertia) GET
     * would fail resolving the Vite manifest via @vite(...) in app.blade.php.
     * Sending a matching X-Inertia-Version alongside X-Inertia avoids the
     * middleware's stale-version 409 and gets back Inertia's JSON payload
     * directly — which is all a backend-only test needs.
     *
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
        ];
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function tenant(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        return [$user, $project];
    }

    private function createUrl(Project $project, string $slug = 'go'): Url
    {
        $domain = Domain::create(['project_id' => $project->id, 'name' => "{$slug}.test"]);
        $owner = User::factory()->create();

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $owner->id,
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

    private function makeReport(Project $project, array $overrides = []): ScheduledReport
    {
        return $project->scheduledReports()->create(array_merge([
            'name' => 'Weekly overview',
            'type' => 'project_summary',
            'subject_id' => null,
            'frequency' => 'weekly',
            'weekday' => 1,
            'period' => 'last_7_days',
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => ['a@example.test'],
            'active' => true,
            'next_run_at' => now()->addDay(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Weekly overview',
            'type' => 'project_summary',
            'subject_id' => null,
            'frequency' => 'weekly',
            'weekday' => 1,
            'day_of_month' => null,
            'period' => 'last_7_days',
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => ['a@example.test'],
            'active' => true,
        ], $overrides);
    }

    public function test_member_can_index_reports_for_their_project(): void
    {
        [$user, $project] = $this->tenant();
        $this->makeReport($project);

        $this->actingAs($user)
            ->get(route('app.project.reports.index', ['project' => $project->id]), $this->inertiaHeaders())
            ->assertOk();
    }

    public function test_member_can_view_create_page(): void
    {
        [$user, $project] = $this->tenant();

        $this->actingAs($user)
            ->get(route('app.project.reports.create', ['project' => $project->id]), $this->inertiaHeaders())
            ->assertOk();
    }

    public function test_store_with_project_summary_type_and_subject_id_is_rejected(): void
    {
        [$user, $project] = $this->tenant();

        $response = $this->actingAs($user)->postJson(
            route('app.project.reports.store', ['project' => $project->id]),
            $this->payload(['type' => 'project_summary', 'subject_id' => 'not-null']),
            ['X-Inertia' => 'true'],
        );

        $response->assertJsonValidationErrors(['subject_id']);
        $this->assertDatabaseCount('scheduled_reports', 0);
    }

    public function test_store_with_link_type_and_valid_link_persists_and_sets_future_next_run_at(): void
    {
        [$user, $project] = $this->tenant();
        $url = $this->createUrl($project);

        $response = $this->actingAs($user)->postJson(
            route('app.project.reports.store', ['project' => $project->id]),
            $this->payload(['type' => 'link', 'subject_id' => $url->id]),
            ['X-Inertia' => 'true'],
        );

        $response->assertRedirect(route('app.project.reports.index'));

        $report = ScheduledReport::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('link', $report->type);
        $this->assertSame($url->id, $report->subject_id);
        $this->assertSame($user->id, $report->created_by);
        $this->assertNotNull($report->next_run_at);
        $this->assertTrue($report->next_run_at->isFuture());
    }

    public function test_store_with_subject_id_from_another_project_is_rejected(): void
    {
        [$user, $project] = $this->tenant();
        $otherProject = Project::create(['name' => 'Other']);
        $foreignUrl = $this->createUrl($otherProject, 'foreign');

        $response = $this->actingAs($user)->postJson(
            route('app.project.reports.store', ['project' => $project->id]),
            $this->payload(['type' => 'link', 'subject_id' => $foreignUrl->id]),
            ['X-Inertia' => 'true'],
        );

        $response->assertJsonValidationErrors(['subject_id']);
        $this->assertDatabaseCount('scheduled_reports', 0);
    }

    public function test_member_can_update_a_report(): void
    {
        [$user, $project] = $this->tenant();
        $report = $this->makeReport($project);

        $response = $this->actingAs($user)->putJson(
            route('app.project.reports.update', ['project' => $project->id, 'report' => $report->id]),
            $this->payload(['name' => 'Renamed report']),
            ['X-Inertia' => 'true'],
        );

        $response->assertRedirect(route('app.project.reports.index'));
        $this->assertSame('Renamed report', $report->fresh()->name);
    }

    public function test_member_can_destroy_a_report(): void
    {
        [$user, $project] = $this->tenant();
        $report = $this->makeReport($project);

        $this->actingAs($user)
            ->delete(route('app.project.reports.destroy', ['project' => $project->id, 'report' => $report->id]))
            ->assertRedirect(route('app.project.reports.index'));

        $this->assertDatabaseMissing('scheduled_reports', ['id' => $report->id]);
    }

    public function test_toggle_flips_active_and_recomputes_next_run_at_on_reactivate(): void
    {
        [$user, $project] = $this->tenant();
        $report = $this->makeReport($project, ['active' => true]);

        $this->actingAs($user)
            ->post(route('app.project.reports.toggle', ['project' => $project->id, 'report' => $report->id]))
            ->assertRedirect();

        $this->assertFalse($report->fresh()->active);

        $this->actingAs($user)
            ->post(route('app.project.reports.toggle', ['project' => $project->id, 'report' => $report->id]))
            ->assertRedirect();

        $fresh = $report->fresh();
        $this->assertTrue($fresh->active);
        $this->assertNotNull($fresh->next_run_at);
        $this->assertTrue($fresh->next_run_at->isFuture());
    }

    public function test_send_now_dispatches_job_with_only_the_callers_email(): void
    {
        Queue::fake();

        [$user, $project] = $this->tenant();
        $report = $this->makeReport($project, ['recipients' => ['someone-else@example.test']]);

        $this->actingAs($user)
            ->post(route('app.project.reports.send-now', ['project' => $project->id, 'report' => $report->id]))
            ->assertRedirect();

        Queue::assertPushed(SendScheduledReport::class, function (SendScheduledReport $job) use ($report, $user) {
            return $job->report->is($report)
                && $job->overrideRecipients === [$user->email];
        });
    }

    // ── Tenant scoping ──────────────────────────────────────────────────

    public function test_cannot_edit_another_projects_report(): void
    {
        [$user, $project] = $this->tenant();
        $otherProject = Project::create(['name' => 'Other']);
        $foreignReport = $this->makeReport($otherProject);

        $this->actingAs($user)
            ->get(route('app.project.reports.edit', ['project' => $project->id, 'report' => $foreignReport->id]))
            ->assertNotFound();
    }

    public function test_cannot_update_another_projects_report(): void
    {
        [$user, $project] = $this->tenant();
        $otherProject = Project::create(['name' => 'Other']);
        $foreignReport = $this->makeReport($otherProject);

        $this->actingAs($user)->putJson(
            route('app.project.reports.update', ['project' => $project->id, 'report' => $foreignReport->id]),
            $this->payload(),
            ['X-Inertia' => 'true'],
        )->assertNotFound();
    }

    public function test_cannot_destroy_another_projects_report(): void
    {
        [$user, $project] = $this->tenant();
        $otherProject = Project::create(['name' => 'Other']);
        $foreignReport = $this->makeReport($otherProject);

        $this->actingAs($user)
            ->delete(route('app.project.reports.destroy', ['project' => $project->id, 'report' => $foreignReport->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('scheduled_reports', ['id' => $foreignReport->id]);
    }

    public function test_cannot_toggle_another_projects_report(): void
    {
        [$user, $project] = $this->tenant();
        $otherProject = Project::create(['name' => 'Other']);
        $foreignReport = $this->makeReport($otherProject);

        $this->actingAs($user)
            ->post(route('app.project.reports.toggle', ['project' => $project->id, 'report' => $foreignReport->id]))
            ->assertNotFound();
    }

    public function test_cannot_send_now_another_projects_report(): void
    {
        Queue::fake();

        [$user, $project] = $this->tenant();
        $otherProject = Project::create(['name' => 'Other']);
        $foreignReport = $this->makeReport($otherProject);

        $this->actingAs($user)
            ->post(route('app.project.reports.send-now', ['project' => $project->id, 'report' => $foreignReport->id]))
            ->assertNotFound();

        Queue::assertNotPushed(SendScheduledReport::class);
    }

    public function test_existing_reports_download_route_still_resolves(): void
    {
        [, $project] = $this->tenant();

        $url = route('app.project.reports.download', ['project' => $project->id]);

        $this->assertStringContainsString('/reports/download', $url);
    }
}
