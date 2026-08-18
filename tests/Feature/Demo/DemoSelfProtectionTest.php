<?php

namespace Tests\Feature\Demo;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSelfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function demoUser(): User
    {
        $user = User::factory()->create(['email' => config('demo.email')]);
        $user->super_admin = true;
        $user->save();

        return $user;
    }

    /**
     * A second super admin, distinct from the demo account, so that any guard
     * keyed on "the actor is deleting/editing/removing themselves" can never
     * fire and mask the behaviour under test.
     */
    private function otherSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();

        return $user;
    }

    public function test_demo_account_cannot_be_deleted(): void
    {
        config(['demo.enabled' => true]);
        $demo = $this->demoUser();
        $actor = $this->otherSuperAdmin();

        $this->actingAs($actor)
            ->delete(route('app.admin.users.destroy', ['user' => $demo->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $demo->id]);
    }

    public function test_demo_account_cannot_be_updated(): void
    {
        config(['demo.enabled' => true]);
        $demo = $this->demoUser();
        $actor = $this->otherSuperAdmin();

        $this->actingAs($actor)
            ->put(route('app.admin.users.update', ['user' => $demo->id]), [
                'name' => 'Someone Else',
                'email' => 'someone-else@example.com',
                'super_admin' => false,
                'force_password_change' => false,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $demo->id, 'email' => config('demo.email')]);
    }

    public function test_other_users_can_still_be_deleted(): void
    {
        config(['demo.enabled' => true]);
        $other = User::factory()->create();

        $this->actingAs($this->demoUser())
            ->delete(route('app.admin.users.destroy', ['user' => $other->id]))
            ->assertSessionMissing('error');
    }

    public function test_demo_project_cannot_be_deleted(): void
    {
        config(['demo.enabled' => true]);
        $demo = $this->demoUser();
        $project = Project::create(['name' => 'Demo', 'locked' => false]);
        $project->users()->attach($demo, ['role' => 'admin', 'active' => true]);

        $this->actingAs($demo)
            ->delete(route('app.admin.projects.destroy', ['project' => $project->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_demo_account_cannot_be_removed_from_project_by_another_member(): void
    {
        config(['demo.enabled' => true]);
        $demo = $this->demoUser();
        $actor = $this->otherSuperAdmin();
        $project = Project::create(['name' => 'Demo', 'locked' => false]);
        // Member role, not admin: keeps the controller's own "last admin"
        // guard out of the picture, so only PROTECTED_ROUTES can deny this.
        $project->users()->attach($demo, ['role' => 'member', 'active' => true]);

        $this->actingAs($actor)
            ->delete(route('app.project.team.members.destroy', [
                'project' => $project->id,
                'user' => $demo->id,
            ]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $demo->id,
        ]);
    }
}
