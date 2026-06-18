<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class ReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_member_can_download_project_report(): void
    {
        Pdf::fake();
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $user->projects()->attach($project, ['role' => 'member']);

        $response = $this->actingAs($user)->get(route('app.project.reports.download', ['project' => $project->id, 'range' => 30]));

        $response->assertOk();
        Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'reports.project');
    }

    public function test_member_can_download_link_report(): void
    {
        Pdf::fake();
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $user->projects()->attach($project, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'x.test']);
        $url = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'test-slug',
            'url' => 'https://example.com',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ]);

        $response = $this->actingAs($user)->get(route('app.project.links.reports.download', ['project' => $project->id, 'url' => $url->id]));

        $response->assertOk();
        Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'reports.link');
    }

    public function test_non_member_is_forbidden(): void
    {
        Pdf::fake();
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('app.project.reports.download', ['project' => $project->id]))
            ->assertForbidden();
    }

    public function test_invalid_custom_range_returns_422(): void
    {
        Pdf::fake();
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $user->projects()->attach($project, ['role' => 'member']);

        $this->actingAs($user)
            ->get(route('app.project.reports.download', ['project' => $project->id, 'from' => '2026-04-30', 'to' => '2026-04-01']))
            ->assertStatus(422);
    }
}
