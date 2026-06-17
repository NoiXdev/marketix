<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->super_admin = true;
        $u->save();

        return $u;
    }

    public function test_non_super_admin_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.projects.index'))
            ->assertForbidden();
    }

    public function test_index_lists_projects(): void
    {
        $admin = $this->superAdmin();
        Project::factory()->count(2)->create();

        $this->actingAs($admin)->get(route('app.admin.projects.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Projects/Index')->has('projects.data'));
    }

    public function test_can_create_project(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('app.admin.projects.store'), ['name' => 'New Project', 'locked' => false])
            ->assertRedirect(route('app.admin.projects.index'));

        $this->assertDatabaseHas('projects', ['name' => 'New Project']);
    }

    public function test_can_soft_delete_project(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->delete(route('app.admin.projects.destroy', ['project' => $project->id]))
            ->assertRedirect(route('app.admin.projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_edit_shows_members_and_assignable_users(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)->get(route('app.admin.projects.edit', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Projects/Edit')
                ->where('members.0.email', $member->email)
                ->has('assignableUsers'));
    }

    public function test_can_assign_user_to_project(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('app.admin.projects.members.store', ['project' => $project->id]), [
            'user_id' => $user->id,
            'role' => 'admin',
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->canAccessProject($project));
        $this->assertSame(ProjectRole::Admin, $user->fresh()->roleInProject($project));
    }

    public function test_can_change_and_remove_member(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)->patch(route('app.admin.projects.members.update', ['project' => $project->id, 'user' => $user->id]), [
            'role' => 'admin',
        ])->assertRedirect();
        $this->assertSame(ProjectRole::Admin, $user->fresh()->roleInProject($project));

        $this->actingAs($admin)->delete(route('app.admin.projects.members.destroy', ['project' => $project->id, 'user' => $user->id]))
            ->assertRedirect();
        $this->assertFalse($user->fresh()->canAccessProject($project));
    }

    public function test_super_admin_can_detach_and_demote_sole_project_admin(): void
    {
        // Intentional design: the admin area has NO last-admin guard. Super admins
        // are the recovery path for locked-out projects. This test pins that design.
        $superAdmin = $this->superAdmin();
        $project = Project::factory()->create();
        $soleAdmin = User::factory()->create();
        $project->users()->attach($soleAdmin, ['role' => ProjectRole::Admin->value, 'active' => true]);

        // Demote sole admin → member (no last-admin guard in admin area)
        $this->actingAs($superAdmin)
            ->patch(route('app.admin.projects.members.update', ['project' => $project->id, 'user' => $soleAdmin->id]), [
                'role' => 'member',
            ])
            ->assertRedirect();
        $this->assertSame(ProjectRole::Member, $soleAdmin->fresh()->roleInProject($project));

        // Detach sole remaining user entirely (no last-admin guard in admin area)
        $this->actingAs($superAdmin)
            ->delete(route('app.admin.projects.members.destroy', ['project' => $project->id, 'user' => $soleAdmin->id]))
            ->assertRedirect();
        $this->assertFalse($soleAdmin->fresh()->canAccessProject($project));
    }
}
