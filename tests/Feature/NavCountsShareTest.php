<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NavCountsShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_navCounts_populates_with_link_count_on_project_page(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);

        // Create 3 URLs for the project
        Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'link1',
            'url' => 'https://example.com/1',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'link2',
            'url' => 'https://example.com/2',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'link3',
            'url' => 'https://example.com/3',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navCounts.links', 3));
    }

    public function test_navCounts_empty_when_no_project(): void
    {
        $this->get(route('app.auth.show-login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navCounts', []));
    }

    public function test_navCounts_empty_when_project_has_no_urls(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Empty Project']);
        $user->projects()->attach($project);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navCounts', ['links' => 0]));
    }
}
