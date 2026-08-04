<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtmColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_persists_utm_columns(): void
    {
        $site = Site::factory()->create();

        $visit = Visit::factory()->forSite($site)->withUtm([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer',
            'utm_term' => 'shoes',
            'utm_content' => 'ad-a',
        ])->create();

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer',
            'utm_term' => 'shoes',
            'utm_content' => 'ad-a',
        ]);
    }

    public function test_page_view_persists_utm_columns(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create();

        $pv = PageView::factory()->forVisit($visit)->withUtm([
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
        ])->create();

        $this->assertDatabaseHas('page_views', [
            'id' => $pv->id,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
        ]);
    }

    public function test_utm_columns_default_to_null(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create();

        $this->assertNull($visit->fresh()->utm_source);
        $this->assertNull(PageView::factory()->forVisit($visit)->create()->fresh()->utm_campaign);
    }
}
