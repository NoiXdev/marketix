<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LinkStatsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project, 2: Url}
     */
    private function makeProjectWithUrl(string $slug = 'promo'): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);

        $url = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        return [$user, $project, $url];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $project, $url] = $this->makeProjectWithUrl();

        $this->get(route('app.project.links.show', ['project' => $project->id, 'url' => $url->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_it_shows_the_link_with_its_stats(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl();

        Statistic::factory()->forUrl($url)->country('Germany')->create(['visitor_hash' => hash('sha256', '10.0.0.1'), 'browser' => 'Chrome']);
        Statistic::factory()->forUrl($url)->country('Germany')->create(['visitor_hash' => hash('sha256', '10.0.0.2'), 'browser' => 'Chrome']);

        $this->actingAs($user)
            ->get(route('app.project.links.show', ['project' => $project->id, 'url' => $url->id]).'?days=7')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Links/Show')
                ->where('days', 7)
                ->where('link.id', $url->id)
                ->where('link.slug', 'promo')
                ->where('link.domain.name', 'links.test')
                ->where('rangeClicks', 2)
                ->where('rangeUnique', 2)
                ->has('clicksByDay', 7)
                ->where('topCountries.0.country', 'Germany')
                ->where('topCountries.0.count', 2)
                ->where('topBrowsers.0.browser', 'Chrome')
                ->has('recentClicks', 2)
            );
    }

    public function test_stats_are_scoped_to_the_single_link(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl('a');

        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);
        $other = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'b',
            'url' => 'https://example.com/other',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.1')]);
        Statistic::factory()->forUrl($other)->create(['visitor_hash' => hash('sha256', '10.0.0.2')]);
        Statistic::factory()->forUrl($other)->create(['visitor_hash' => hash('sha256', '10.0.0.3')]);

        $this->actingAs($user)
            ->get(route('app.project.links.show', ['project' => $project->id, 'url' => $url->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rangeClicks', 1));
    }

    public function test_a_user_cannot_view_a_link_from_another_project(): void
    {
        [$user] = $this->makeProjectWithUrl('mine');
        [, $otherProject, $otherUrl] = $this->makeProjectWithUrl('theirs');

        $this->actingAs($user)
            ->get(route('app.project.links.show', ['project' => $otherProject->id, 'url' => $otherUrl->id]))
            ->assertForbidden();
    }

    public function test_requesting_own_project_with_a_foreign_url_id_is_not_found(): void
    {
        // User accesses a project they DO belong to, but with a url id that
        // belongs to a different project. Middleware lets them in; the
        // $project->urls() scope must then 404 the foreign url.
        [$user, $project] = $this->makeProjectWithUrl('mine');
        [, , $foreignUrl] = $this->makeProjectWithUrl('theirs');

        $this->actingAs($user)
            ->get(route('app.project.links.show', ['project' => $project->id, 'url' => $foreignUrl->id]))
            ->assertNotFound();
    }
}
