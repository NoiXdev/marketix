<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SiteCrudTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProject(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_index_lists_project_sites(): void
    {
        [$user, $project] = $this->userWithProject();
        Site::factory()->forProject($project)->create(['name' => 'Marketing']);

        $this->actingAs($user)
            ->get(route('app.project.sites.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sites/Index')
                ->has('sites', 1)
                ->where('sites.0.name', 'Marketing')
            );
    }

    public function test_create_form_does_not_offer_own_banner_consent_mode(): void
    {
        [$user, $project] = $this->userWithProject();

        $this->actingAs($user)
            ->get(route('app.project.sites.create', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sites/Create')
                ->where('consentModes', fn ($modes) => collect($modes)->doesntContain('value', 'own_banner')
                    && collect($modes)->contains('value', 'immediate')
                    && collect($modes)->contains('value', 'third_party_signal'))
            );
    }

    public function test_own_banner_consent_mode_is_rejected_by_validation(): void
    {
        [$user, $project] = $this->userWithProject();

        $this->actingAs($user)
            ->post(route('app.project.sites.store', ['project' => $project->id]), [
                'name' => 'Shop',
                'domain' => 'shop.example.com',
                'tracking_mode' => 'cookie',
                'consent_mode' => 'own_banner',
                'respect_dnt' => false,
            ])
            ->assertSessionHasErrors('consent_mode');

        $this->assertDatabaseMissing('sites', ['domain' => 'shop.example.com']);
    }

    public function test_store_creates_a_site(): void
    {
        [$user, $project] = $this->userWithProject();

        $this->actingAs($user)
            ->post(route('app.project.sites.store', ['project' => $project->id]), [
                'name' => 'Shop',
                'domain' => 'shop.example.com',
                'tracking_mode' => 'cookieless',
                'consent_mode' => 'immediate',
                'respect_dnt' => false,
            ])
            ->assertRedirect(route('app.project.sites.index', ['project' => $project->id]));

        $this->assertDatabaseHas('sites', ['project_id' => $project->id, 'name' => 'Shop']);
    }

    public function test_update_changes_mode(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create();

        $this->actingAs($user)
            ->put(route('app.project.sites.update', ['project' => $project->id, 'site' => $site->id]), [
                'name' => $site->name,
                'domain' => $site->domain,
                'tracking_mode' => 'cookie',
                'consent_mode' => 'third_party_signal',
                'consent_signal' => 'UC_UI',
                'respect_dnt' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'tracking_mode' => 'cookie', 'consent_signal' => 'UC_UI']);
    }

    public function test_destroy_deletes_site(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create();

        $this->actingAs($user)
            ->delete(route('app.project.sites.destroy', ['project' => $project->id, 'site' => $site->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('sites', ['id' => $site->id]);
    }

    public function test_foreign_project_site_is_not_found(): void
    {
        [$user, $project] = $this->userWithProject();
        $otherSite = Site::factory()->create(); // different project

        $this->actingAs($user)
            ->get(route('app.project.sites.edit', ['project' => $project->id, 'site' => $otherSite->id]))
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $project] = $this->userWithProject();
        $this->get(route('app.project.sites.index', ['project' => $project->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }
}
