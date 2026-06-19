<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_change_is_logged(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)->patch(route('app.project.team.members.update', ['project' => $project->id, 'user' => $member->id]), [
            'role' => ProjectRole::Admin->value,
        ]);

        $activity = Activity::query()->where('log_name', 'membership')->where('description', 'role_changed')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame($project->id, $activity->project_id);
        $this->assertTrue($activity->causer->is($admin));
    }

    public function test_member_removal_is_logged(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)->delete(route('app.project.team.members.destroy', ['project' => $project->id, 'user' => $member->id]));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'membership',
            'description' => 'member_removed',
            'project_id' => $project->id,
        ]);
    }

    public function test_invitation_sent_is_logged(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->actingAs($admin)->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
            'email' => 'invitee@test.com',
            'role' => ProjectRole::Member->value,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'invitation',
            'description' => 'invitation_sent',
            'project_id' => $project->id,
        ]);
    }
}
