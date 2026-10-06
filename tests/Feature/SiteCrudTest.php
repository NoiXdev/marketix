<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\SiteOverview;
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

    public function test_index_defers_per_site_stats(): void
    {
        [$user, $project] = $this->userWithProject();
        $tracked = Site::factory()->forProject($project)->create(['name' => 'Tracked']);
        $untracked = Site::factory()->forProject($project)->create(['name' => 'Untracked']);
        $visit = Visit::factory()->forSite($tracked)->create([
            'visitor_hash' => 'v1',
            'started_at' => now()->subDay(),
            'last_activity_at' => now()->subDay(),
        ]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'created_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('app.project.sites.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sites.0.tracking_mode_label')
                ->missing('stats')
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where("stats.{$tracked->id}.visitors", 1)
                    ->where("stats.{$tracked->id}.previous_visitors", 0)
                    ->where("stats.{$tracked->id}.page_views", 1)
                    ->has("stats.{$tracked->id}.trend", SiteOverview::DAYS)
                    ->has("stats.{$tracked->id}.last_seen_at")
                    ->where("stats.{$untracked->id}", null)
                )
            );
    }

    public function test_edit_page_shows_the_tracking_snippet(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create();

        $this->actingAs($user)
            ->get(route('app.project.sites.edit', ['project' => $project->id, 'site' => $site->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sites/Edit')
                ->where('snippet', '<script defer data-site="'.$site->tracking_id.'" src="'.rtrim(config('app.url'), '/').'/mx.js"></script>')
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
            ->assertRedirect(route('app.project.analytics.show', ['project' => $project->id, 'site' => Site::where('name', 'Shop')->value('id')]));

        $this->assertDatabaseHas('sites', ['project_id' => $project->id, 'name' => 'Shop']);
    }

    public function test_waiting_for_consent_requires_a_signal_name(): void
    {
        [$user, $project] = $this->userWithProject();

        $this->actingAs($user)
            ->post(route('app.project.sites.store', ['project' => $project->id]), [
                'name' => 'Shop',
                'domain' => 'shop.example.com',
                'tracking_mode' => 'cookie',
                'consent_mode' => 'third_party_signal',
                'consent_signal' => '',
            ])
            ->assertSessionHasErrors('consent_signal');
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

    public function test_update_saves_enhanced_measurement_settings(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create();

        $this->actingAs($user)
            ->put(route('app.project.sites.update', ['project' => $project->id, 'site' => $site->id]), [
                'name' => $site->name,
                'domain' => $site->domain,
                'tracking_mode' => 'cookieless',
                'consent_mode' => 'immediate',
                'track_outbound_links' => false,
                'track_file_downloads' => true,
                'site_search_params' => ' q , search,, q ',
            ])
            ->assertRedirect();

        $site->refresh();
        $this->assertFalse($site->track_outbound_links);
        $this->assertTrue($site->track_file_downloads);
        $this->assertSame('q,search', $site->site_search_params);
        $this->assertSame(['q', 'search'], $site->searchParams());
    }

    public function test_blank_search_params_disable_site_search_and_invalid_ones_are_rejected(): void
    {
        [$user, $project] = $this->userWithProject();
        $site = Site::factory()->forProject($project)->create(['site_search_params' => 'q']);
        $route = route('app.project.sites.update', ['project' => $project->id, 'site' => $site->id]);
        $payload = ['name' => $site->name, 'domain' => $site->domain, 'tracking_mode' => 'cookieless', 'consent_mode' => 'immediate'];

        $this->actingAs($user)->put($route, $payload + ['site_search_params' => 'q=1&x'])->assertSessionHasErrors('site_search_params');

        $this->actingAs($user)->put($route, $payload + ['site_search_params' => '  '])->assertRedirect();
        $this->assertNull($site->refresh()->site_search_params);
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
