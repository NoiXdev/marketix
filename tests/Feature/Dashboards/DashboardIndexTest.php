<?php

namespace Tests\Feature\Dashboards;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardIndexTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_first_visit_lazily_creates_default_and_returns_it(): void
    {
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Dashboard')
                ->has('dashboards', 1)
                ->where('active.name', 'Overview')
                ->has('active.widgets'));

        $this->assertSame(1, $user->dashboards()->where('project_id', $project->id)->count());
    }
}
