<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VersionShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_shares_version_from_package_json(): void
    {
        $expected = json_decode(file_get_contents(base_path('package.json')))->version;

        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('version', $expected));
    }

    public function test_inertia_shares_version_on_guest_auth_pages(): void
    {
        $expected = json_decode(file_get_contents(base_path('package.json')))->version;

        $this->get(route('app.auth.show-login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('version', $expected));
    }
}
