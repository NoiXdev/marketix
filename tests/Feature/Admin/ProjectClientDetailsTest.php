<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectClientDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_store_client_details_on_a_project(): void
    {
        $admin = User::factory()->create(['super_admin' => true]);
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->put(route('app.admin.projects.update', ['project' => $project->id]), [
                'name' => $project->name,
                'locked' => false,
                'client_name' => 'Acme Corp GmbH',
                'client_address' => 'Musterstraße 1, 50672 Köln',
            ])
            ->assertRedirect();

        $fresh = $project->fresh();
        $this->assertSame('Acme Corp GmbH', $fresh->client_name);
        $this->assertSame('Musterstraße 1, 50672 Köln', $fresh->client_address);
    }

    public function test_a_project_admin_cannot_reach_the_admin_project_screen(): void
    {
        $project = Project::factory()->create();
        $projectAdmin = User::factory()->create(['super_admin' => false]);
        $project->users()->attach($projectAdmin, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->actingAs($projectAdmin)
            ->get(route('app.admin.projects.edit', ['project' => $project->id]))
            ->assertForbidden();
    }

    public function test_changing_the_client_name_is_written_to_the_activity_log(): void
    {
        $admin = User::factory()->create(['super_admin' => true]);
        $project = Project::factory()->create(['client_name' => 'Old Client']);

        $this->actingAs($admin)
            ->put(route('app.admin.projects.update', ['project' => $project->id]), [
                'name' => $project->name,
                'locked' => false,
                'client_name' => 'New Client',
                'client_address' => null,
            ])
            ->assertRedirect();

        $activity = Activity::query()
            ->where('subject_id', $project->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $attrs = $activity->attribute_changes->toArray()['attributes'];
        $this->assertArrayHasKey('client_name', $attrs);
        $this->assertSame('New Client', $attrs['client_name']);
    }
}
