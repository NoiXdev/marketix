<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_page_with_analysis_links_and_status_issue(): void
    {
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test']);
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'x.test');

        $html = '<html><head><title>Home</title></head><body>'
            .'<h1>Home</h1><a href="/about">About</a><a href="https://other.test">Ext</a>'
            .str_repeat('word ', 150).'</body></html>';

        $page = $observer->recordResponse('https://x.test/', 200, ['Content-Type' => ['text/html']], $html, 123.0);

        $this->assertSame(200, $page->status_code);
        $this->assertSame('Home', $page->title);
        $this->assertSame(['level' => 1, 'text' => 'Home'], $page->headings[0]);
        $this->assertSame(2, $page->outLinks()->count());
        $this->assertSame(1, $crawl->fresh()->pages_crawled);
    }

    public function test_4xx_gets_client_error_issue(): void
    {
        $crawl = Crawl::factory()->create();
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'x.test');

        $page = $observer->recordResponse('https://x.test/missing', 404, [], '', 10.0);

        $this->assertContains('client_error', $page->issues);
    }
}
