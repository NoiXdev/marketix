<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PruneAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_rows_older_than_config_default(): void
    {
        config(['analytics.retention_months' => 24]);
        $site = Site::factory()->create(['retention_days' => null]);

        $oldVisit = Visit::factory()->forSite($site)->create([
            'started_at' => now()->subMonths(25),
            'last_activity_at' => now()->subMonths(25),
        ]);
        PageView::factory()->forVisit($oldVisit)->create(['created_at' => now()->subMonths(25)]);

        $freshVisit = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($freshVisit)->create(['created_at' => now()]);

        $this->artisan('analytics:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('page_views', ['id' => $oldVisit->pageViews()->first()?->id]);
        $this->assertSame(1, PageView::count());
        $this->assertSame(1, Visit::count());
    }

    public function test_per_site_retention_days_override_is_honored(): void
    {
        $site = Site::factory()->create(['retention_days' => 10]);
        $visit = Visit::factory()->forSite($site)->create([
            'started_at' => now()->subDays(11),
            'last_activity_at' => now()->subDays(11),
        ]);
        PageView::factory()->forVisit($visit)->create(['created_at' => now()->subDays(11)]);

        $this->artisan('analytics:prune')->assertExitCode(0);

        $this->assertSame(0, PageView::count());
    }
}
