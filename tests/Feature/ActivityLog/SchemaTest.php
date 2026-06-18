<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_has_project_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('activity_log', 'project_id'));
    }

    public function test_configured_activity_model_is_custom(): void
    {
        $this->assertSame(Activity::class, config('activitylog.activity_model'));
    }

    public function test_project_id_is_persisted_and_related(): void
    {
        $project = Project::factory()->create();
        $activity = activity()->log('test');
        $activity->project_id = $project->id;
        $activity->save();

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertTrue($activity->fresh()->project->is($project));
    }
}
