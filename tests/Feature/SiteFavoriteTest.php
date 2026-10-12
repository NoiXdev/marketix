<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SiteFavoriteTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProject(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_favorite_sites_are_listed_first(): void
    {
        [$user, $project] = $this->userWithProject();
        $oldest = Site::factory()->forProject($project)->create(['name' => 'Oldest', 'created_at' => now()->subDays(3)]);
        Site::factory()->forProject($project)->create(['name' => 'Middle', 'created_at' => now()->subDays(2)]);
        Site::factory()->forProject($project)->create(['name' => 'Newest', 'created_at' => now()->subDay()]);
        $user->favoriteSites()->attach($oldest);

        $this->actingAs($user)
            ->get(route('app.project.sites.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sites.0.name', 'Oldest')
                ->where('sites.0.is_favorite', true)
                ->where('sites.1.name', 'Newest')
                ->where('sites.1.is_favorite', false)
                ->where('sites.2.name', 'Middle')
            );
    }

    public function test_favorites_are_per_user(): void
    {
        [$user, $project] = $this->userWithProject();
        $colleague = User::factory()->create();
        $colleague->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();
        $colleague->favoriteSites()->attach($site);

        $this->actingAs($user)
            ->get(route('app.project.sites.index', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('sites.0.is_favorite', false));
    }

    public function test_store_marks_site_as_favorite(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create();

        $this->actingAs($user)
            ->post(route('app.project.sites.favorite.store', ['project' => $project->id, 'site' => $site->id]))
            ->assertRedirect(route('app.project.sites.index', ['project' => $project->id]));
        $this->actingAs($user)
            ->post(route('app.project.sites.favorite.store', ['project' => $project->id, 'site' => $site->id]));

        $this->assertDatabaseCount('site_favorites', 1);
        $this->assertTrue($user->favoriteSites()->whereKey($site->id)->exists());
    }

    public function test_destroy_removes_favorite(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create();
        $user->favoriteSites()->attach($site);

        $this->actingAs($user)
            ->delete(route('app.project.sites.favorite.destroy', ['project' => $project->id, 'site' => $site->id]))
            ->assertRedirect(route('app.project.sites.index', ['project' => $project->id]));

        $this->assertDatabaseCount('site_favorites', 0);
    }

    public function test_foreign_project_site_cannot_be_favorited(): void
    {
        [$user, $project] = $this->userWithProject();
        $foreign = Site::factory()->create();

        $this->actingAs($user)
            ->post(route('app.project.sites.favorite.store', ['project' => $project->id, 'site' => $foreign->id]))
            ->assertNotFound();

        $this->assertDatabaseCount('site_favorites', 0);
    }
}
