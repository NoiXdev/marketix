<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardClicksChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    /**
     * Create a user that belongs to a fresh project, plus a URL in that
     * project that statistics can hang off of.
     *
     * @return array{0: User, 1: Project, 2: Url}
     */
    private function makeProjectWithUrl(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        $url = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        return [$user, $project, $url];
    }

    private function seedClick(Url $url, string $ip, \DateTimeInterface $at): void
    {
        Statistic::factory()->forUrl($url)->create([
            'ip' => $ip,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function test_dashboard_returns_daily_total_and_unique_clicks(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl();

        // Today: 3 clicks from 2 distinct IPs.
        $this->seedClick($url, '10.0.0.1', now());
        $this->seedClick($url, '10.0.0.1', now());
        $this->seedClick($url, '10.0.0.2', now());

        // Three days ago: 2 clicks from 2 distinct IPs.
        $this->seedClick($url, '10.0.0.3', now()->subDays(3));
        $this->seedClick($url, '10.0.0.4', now()->subDays(3));

        $response = $this->actingAs($user)->get(
            route('app.project.dashboard', ['project' => $project->id]).'?days=7'
        );

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('days', 7)
            ->has('clicksByDay', 7)
            // Continuous window: oldest day is 6 days ago, newest is today.
            ->where('clicksByDay.0.date', now()->subDays(6)->format('Y-m-d'))
            ->where('clicksByDay.6.date', now()->format('Y-m-d'))
            ->where('clicksByDay.6.clicks', 3)
            ->where('clicksByDay.6.unique', 2)
            ->where('clicksByDay.3.clicks', 2)
            ->where('clicksByDay.3.unique', 2)
            ->where('clicksByDay.5.clicks', 0)
            ->where('clicksByDay.5.unique', 0)
        );
    }

    public function test_dashboard_defaults_to_thirty_days_and_validates_window(): void
    {
        [$user, $project] = $this->makeProjectWithUrl();

        $route = route('app.project.dashboard', ['project' => $project->id]);

        // No param -> default 30.
        $this->actingAs($user)->get($route)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('days', 30)
                ->has('clicksByDay', 30));

        // Invalid value -> falls back to 30.
        $this->actingAs($user)->get($route.'?days=999')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('days', 30));

        // Allowed extended window.
        $this->actingAs($user)->get($route.'?days=365')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('days', 365)
                ->has('clicksByDay', 365));
    }
}
