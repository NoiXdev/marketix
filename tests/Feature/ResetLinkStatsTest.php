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
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ResetLinkStatsTest extends TestCase
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
            'clicks' => 5,
            'unique_clicks' => 3,
        ]);

        return [$user, $project, $url];
    }

    public function test_it_permanently_deletes_statistics_and_zeroes_counters(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl();

        Statistic::factory()->forUrl($url)->create(['ip' => '10.0.0.1']);
        Statistic::factory()->forUrl($url)->create(['ip' => '10.0.0.2']);

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $url->id]))
            ->assertRedirect();

        // Rows are force-deleted (not just soft-deleted) — table is empty.
        $this->assertDatabaseCount('statistics', 0);

        $url->refresh();
        $this->assertSame(0, (int) $url->clicks);
        $this->assertSame(0, (int) $url->unique_clicks);
    }

    public function test_it_records_a_stats_reset_activity_entry(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl();
        Statistic::factory()->forUrl($url)->create(['ip' => '10.0.0.1']);

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $url->id]));

        $this->assertTrue(
            Activity::where('event', 'stats_reset')
                ->where('subject_id', $url->id)
                ->where('causer_id', $user->id)
                ->exists()
        );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $project, $url] = $this->makeProjectWithUrl();

        $this->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $url->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_a_user_cannot_reset_a_link_in_another_project(): void
    {
        [$user] = $this->makeProjectWithUrl('mine');
        [, $otherProject, $otherUrl] = $this->makeProjectWithUrl('theirs');

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $otherProject->id, 'url' => $otherUrl->id]))
            ->assertForbidden();
    }

    public function test_resetting_a_foreign_url_in_own_project_is_not_found(): void
    {
        [$user, $project] = $this->makeProjectWithUrl('mine');
        [, , $foreignUrl] = $this->makeProjectWithUrl('theirs');

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $foreignUrl->id]))
            ->assertNotFound();
    }
}
