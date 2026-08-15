<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverUrlTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    public function test_url_hygiene_issues_recorded_for_html_page(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/Foo_Bar',
            200,
            ['Content-Type' => ['text/html']],
            '<html><head><title>t</title></head><body>x</body></html>',
            5.0,
        );

        $this->assertContains('url_uppercase', $page->issues);
        $this->assertContains('url_underscores', $page->issues);
    }

    public function test_url_hygiene_issues_recorded_for_non_html_asset(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/My%20Image.png',
            200,
            ['Content-Type' => ['image/png']],
            'PNGDATA',
            5.0,
        );

        // Coverage extends to assets: the space is flagged even though no analyzer ran.
        $this->assertContains('url_contains_space', $page->issues);
    }
}
