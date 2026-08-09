<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageViewModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_visit_with_a_page_view(): void
    {
        $site = Site::factory()->create();

        $visit = Visit::factory()->forSite($site)->create([
            'entry_path' => '/home',
            'exit_path' => '/home',
            'pageview_count' => 1,
        ]);

        $pv = PageView::factory()->forVisit($visit)->create(['path' => '/home']);

        $this->assertSame($site->id, $pv->site->id);
        $this->assertSame($visit->id, $pv->visit->id);
        $this->assertSame(1, $visit->fresh()->pageview_count);
        $this->assertTrue($visit->pageViews()->whereKey($pv->id)->exists());
    }
}
