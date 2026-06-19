<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\PixelProvider;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentModelLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a Domain dispatches RegenerateTraefikConfigJob, which writes
        // to a host path absent in CI. Fake only that job so it never runs.
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_domain_logs_editable_fields_and_ignores_health_churn(): void
    {
        $project = Project::factory()->create();
        $domain = $project->domains()->create(['name' => 'a.test', 'redirect_root' => null, 'redirect_not_found' => null]);

        $domain->update(['redirect_root' => 'https://x.test']);
        $domain->update(['dns_ok' => true, 'last_checked_at' => now()]);

        $editActivity = Activity::query()->where('subject_id', $domain->id)->where('event', 'updated')->latest('id')->first();
        // Spatie v5: diffs live in attribute_changes, not properties.
        $attrs = $editActivity->attribute_changes->toArray()['attributes'];

        $this->assertSame('domain', $editActivity->log_name);
        $this->assertSame($project->id, $editActivity->project_id);
        // The health-only update must not create a new logged activity.
        $this->assertArrayHasKey('redirect_root', $attrs);
        $this->assertArrayNotHasKey('dns_ok', $attrs);
        $this->assertSame(1, Activity::query()->where('subject_id', $domain->id)->where('event', 'updated')->count());
    }

    public function test_qrcode_logs_activity(): void
    {
        $project = Project::factory()->create();
        $qr = $project->qrCodes()->create(['name' => 'My QR', 'type' => 'url', 'is_dynamic' => false, 'content' => ['url' => 'https://x.test'], 'style' => []]);

        $activity = Activity::query()->where('subject_id', $qr->id)->latest('id')->first();
        $this->assertSame('qrcode', $activity->log_name);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_pixel_logs_activity(): void
    {
        $project = Project::factory()->create();
        $pixel = $project->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => 'P', 'tag' => 'TAG']);

        $activity = Activity::query()->where('subject_id', $pixel->id)->latest('id')->first();
        $this->assertSame('pixel', $activity->log_name);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_project_tags_activity_with_own_id(): void
    {
        $project = Project::factory()->create();
        $project->update(['name' => 'Renamed']);

        $activity = Activity::query()->where('subject_id', $project->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame('project', $activity->log_name);
        $this->assertSame($project->id, $activity->project_id);
    }
}
