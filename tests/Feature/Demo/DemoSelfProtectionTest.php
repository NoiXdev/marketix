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

    public function test_demo_account_cannot_be_deleted(): void
    {
        config(['demo.enabled' => true]);
        $demo = $this->demoUser();

        $this->actingAs($demo)
            ->delete(route('app.admin.users.destroy', ['user' => $demo->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $demo->id]);
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
}
