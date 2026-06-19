<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Project;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_event_is_tagged_and_attributed(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $activity = ActivityRecorder::project('membership', 'member_added', $project->id, $user, $project, ['role' => 'member']);

        $this->assertSame('membership', $activity->log_name);
        $this->assertSame('member_added', $activity->description);
        $this->assertSame($project->id, $activity->project_id);
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame('member', $activity->properties->toArray()['role']);
    }

    public function test_security_event_has_null_project_and_request_metadata(): void
    {
        $user = User::factory()->create();

        $activity = ActivityRecorder::security('login', $user);

        $this->assertSame('security', $activity->log_name);
        $this->assertNull($activity->project_id);
        $this->assertTrue($activity->causer->is($user));
        $this->assertArrayHasKey('ip', $activity->properties->toArray());
        $this->assertArrayHasKey('user_agent', $activity->properties->toArray());
    }
}
