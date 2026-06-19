<?php

namespace Tests\Feature\ActivityLog;

use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UrlLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a Domain dispatches RegenerateTraefikConfigJob, which writes
        // to a host path absent in CI. Fake only that job so it never runs.
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_creating_a_url_logs_a_tagged_activity(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create();

        $activity = Activity::query()->where('subject_id', $url->id)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('url', $activity->log_name);
        $this->assertSame('created', $activity->description);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_updating_a_url_logs_only_dirty_attributes_and_excludes_timestamps(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create(['slug' => 'old-slug']);

        $url->update(['slug' => 'new-slug']);

        $activity = Activity::query()->where('subject_id', $url->id)->where('event', 'updated')->latest('id')->first();
        // Spatie v5: diffs live in attribute_changes, not properties.
        $attrs = $activity->attribute_changes->toArray()['attributes'];

        $this->assertSame('new-slug', $attrs['slug']);
        $this->assertArrayNotHasKey('updated_at', $attrs);
        $this->assertArrayNotHasKey('created_at', $attrs);
        $this->assertArrayNotHasKey('clicks', $attrs);
    }

    public function test_password_is_redacted(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create();

        $url->update(['password' => 'super-secret']);

        $activity = Activity::query()->where('subject_id', $url->id)->where('event', 'updated')->latest('id')->first();
        $attrs = $activity->attribute_changes->toArray()['attributes'];

        $this->assertSame('••••', $attrs['password']);
        $this->assertStringNotContainsString('super-secret', json_encode($activity->attribute_changes->toArray()));
    }

    public function test_ab_targeting_is_logged(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create();

        $url->update(['targeting_ab' => [['url' => 'https://b.test', 'weight' => 50]]]);

        $activity = Activity::query()->where('subject_id', $url->id)->where('event', 'updated')->latest('id')->first();

        $this->assertArrayHasKey('targeting_ab', $activity->attribute_changes->toArray()['attributes']);
    }
}
