<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_site_analytics_with_metrics(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        $visit = Visit::factory()->forSite($site)->create(['visitor_hash' => 'v1', 'pageview_count' => 2]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/home']);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/pricing']);
        PageView::factory()->forSite($site)->create(['visitor_hash' => 'bot', 'is_bot' => true]);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]).'?days=7')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/Index')
                ->where('days', 7)
                ->where('totalPageViews', 2)
                ->where('uniqueVisitors', 1)
                ->has('pageViewsByDay', 7)
                ->has('topPaths')
                ->has('topReferrers')
                ->has('countries')
            );
    }

    public function test_foreign_project_site_is_not_found(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $foreign = Site::factory()->create();

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $foreign->id]))
            ->assertNotFound();
    }
}
