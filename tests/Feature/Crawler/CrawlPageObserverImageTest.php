<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverImageTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    public function test_flags_image_over_100kb(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $big = $this->observer($crawl)->recordResponse('https://example.com/big.png', 200, ['Content-Type' => ['image/png']], str_repeat('x', 102401), 5.0);
        $this->assertContains('image_over_100kb', $big->issues);

        $small = $this->observer($crawl)->recordResponse('https://example.com/small.png', 200, ['Content-Type' => ['image/png']], 'tiny', 5.0);
        $this->assertNotContains('image_over_100kb', $small->issues);
    }
}
