<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserProjectMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->super_admin = true;
        $u->save();

        return $u;
    }

    public function test_admin_can_attach_user_to_project(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.users.projects.store', ['user' => $target->id]), [
                'project_id' => $project->id,
                'role' => 'member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'user_id' => $target->id,
            'project_id' => $project->id,
            'role' => 'member',
            'active' => true,
        ]);
    }

    public function test_admin_can_change_membership_role(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($target->id, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)
            ->patch(route('app.admin.users.projects.update', ['user' => $target->id, 'project' => $project->id]), [
                'role' => 'admin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'user_id' => $target->id,
            'project_id' => $project->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_detach_user_from_project(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($target->id, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)
            ->delete(route('app.admin.users.projects.destroy', ['user' => $target->id, 'project' => $project->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_user', [
            'user_id' => $target->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_edit_page_exposes_memberships_and_available_projects(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $member = Project::factory()->create(['name' => 'Member Project']);
        $other = Project::factory()->create(['name' => 'Other Project']);
        $member->users()->attach($target->id, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->actingAs($admin)
            ->get(route('app.admin.users.edit', ['user' => $target->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Edit')
                ->has('memberships', 1)
                ->where('memberships.0.role', 'admin')
                ->has('availableProjects', 1)
                ->where('availableProjects.0.id', $other->id));
    }

    public function test_membership_routes_require_super_admin(): void
    {
        $target = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.users.projects.store', ['user' => $target->id]), [
                'project_id' => $project->id,
                'role' => 'member',
            ])
            ->assertForbidden();
    }
}
