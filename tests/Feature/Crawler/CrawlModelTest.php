<?php

namespace Tests\Feature\Crawler;

use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_crawl_casts_and_relations(): void
    {
        $project = Project::factory()->create();
        $crawl = Crawl::factory()->for($project)->create([
            'mode' => CrawlMode::FullSite->value,
            'status' => CrawlStatus::Queued->value,
            'summary' => ['missing_title' => 2],
        ]);
        $page = CrawlPage::factory()->for($crawl)->create(['issues' => ['missing_title']]);

        $this->assertInstanceOf(CrawlMode::class, $crawl->mode);
        $this->assertInstanceOf(CrawlStatus::class, $crawl->status);
        $this->assertSame(['missing_title' => 2], $crawl->summary);
        $this->assertSame(['missing_title'], $page->issues);
        $this->assertTrue($crawl->pages->contains($page));
        $this->assertTrue($project->crawls->contains($crawl));
    }
}
