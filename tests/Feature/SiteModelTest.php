<?php

namespace Tests\Feature;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_site_with_generated_tracking_id_and_casts(): void
    {
        $project = Project::create(['name' => 'Acme']);

        $site = Site::create([
            'project_id' => $project->id,
            'name' => 'Marketing Site',
            'domain' => 'example.com',
            'tracking_mode' => TrackingMode::Cookieless,
            'consent_mode' => ConsentMode::Immediate,
        ]);

        $this->assertNotEmpty($site->tracking_id);
        $this->assertInstanceOf(TrackingMode::class, $site->fresh()->tracking_mode);
        $this->assertInstanceOf(ConsentMode::class, $site->fresh()->consent_mode);
        $this->assertFalse($site->fresh()->respect_dnt);
        $this->assertSame($project->id, $site->project->id);
    }

    public function test_tracking_id_is_unique_across_sites(): void
    {
        $project = Project::create(['name' => 'Acme']);
        $a = Site::factory()->forProject($project)->create();
        $b = Site::factory()->forProject($project)->create();

        $this->assertNotSame($a->tracking_id, $b->tracking_id);
    }

    public function test_analytics_retention_default_is_24_months(): void
    {
        $this->assertSame(24, (int) config('analytics.retention_months'));
    }
}
