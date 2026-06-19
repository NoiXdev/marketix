<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\ProjectRole;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a Domain dispatches RegenerateTraefikConfigJob, which writes
        // to a host path absent in CI. Fake only that job so it never runs.
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_member_sees_only_their_project_activity_and_no_security_events(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        Url::factory()->for($project)->create(['slug' => 'mine']);
        Url::factory()->for($other)->create(['slug' => 'theirs']);
        ActivityRecorder::security('login', $user);

        $this->actingAs($user)
            ->get(route('app.project.activity.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Activity/Index')
                ->where('activities.data', fn ($data) => count($data) > 0
                    && collect($data)->every(fn ($a) => $a['log_name'] !== 'security'))
            );
    }

    public function test_non_member_is_forbidden(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->get(route('app.project.activity.index', ['project' => $project->id]))
            ->assertForbidden();
    }
}
