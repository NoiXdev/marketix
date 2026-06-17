<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_in_project_returns_enum(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->assertSame(ProjectRole::Admin, $user->roleInProject($project));
    }

    public function test_role_in_project_null_for_non_member(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->assertNull($user->roleInProject($project));
    }

    public function test_is_project_admin_true_for_admin_role(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->assertTrue($user->isProjectAdmin($project));
    }

    public function test_is_project_admin_false_for_member_role(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->assertFalse($user->isProjectAdmin($project));
    }

    public function test_super_admin_is_project_admin_without_membership(): void
    {
        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();
        $project = Project::factory()->create();

        $this->assertTrue($user->isProjectAdmin($project));
    }

    public function test_dashboard_shares_current_project_role(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('currentProjectRole', 'admin'));
    }

    public function test_super_admin_dashboard_shares_null_role(): void
    {
        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('currentProjectRole', null));
    }
}
