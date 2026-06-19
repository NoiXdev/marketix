<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectChooserTest extends TestCase
{
    use RefreshDatabase;

    public function test_chooser_lists_user_projects_with_roles_sorted_by_name(): void
    {
        $user = User::factory()->create();
        $alpha = Project::factory()->create(['name' => 'Alpha']);
        $beta = Project::factory()->create(['name' => 'Beta']);
        $alpha->users()->attach($user, ['role' => ProjectRole::Admin->value, 'active' => true]);
        $beta->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($user)
            ->get(route('app.projects.choose'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ChooseProject')
                ->has('projects', 2)
                ->where('projects.0.name', 'Alpha')
                ->where('projects.0.role', 'admin')
                ->where('projects.1.name', 'Beta')
                ->where('projects.1.role', 'member')
            );
    }

    public function test_chooser_shows_all_projects_as_admin_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();
        Project::factory()->create(['name' => 'Gamma']);
        Project::factory()->create(['name' => 'Delta']);

        $this->actingAs($user)
            ->get(route('app.projects.choose'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ChooseProject')
                ->has('projects', 2)
                ->where('projects.0.role', 'admin')
                ->where('projects.1.role', 'admin')
            );
    }

    public function test_chooser_renders_empty_list_when_user_has_no_projects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.projects.choose'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ChooseProject')
                ->has('projects', 0)
            );
    }

    public function test_chooser_requires_authentication(): void
    {
        $this->get(route('app.projects.choose'))
            ->assertRedirect('/auth/login');
    }
}
