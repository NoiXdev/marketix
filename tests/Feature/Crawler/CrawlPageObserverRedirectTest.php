<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    private function html(string $head = ''): string
    {
        return "<html><head><title>t</title>{$head}</head><body>x</body></html>";
    }

    public function test_single_redirect_records_internal_redirect_3xx(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/start',
            200,
            ['Content-Type' => ['text/html'], 'X-Guzzle-Redirect-History' => ['https://example.com/final']],
            $this->html(),
            5.0,
        );

        $this->assertContains('internal_redirect_3xx', $page->issues);
        $this->assertNotContains('redirect_chain', $page->issues);
    }

    public function test_two_hop_chain_records_redirect_chain(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/start',
            200,
            ['Content-Type' => ['text/html'], 'X-Guzzle-Redirect-History' => ['https://example.com/mid,https://example.com/final']],
            $this->html(),
            5.0,
        );

        $this->assertContains('redirect_chain', $page->issues);
        $this->assertNotContains('internal_redirect_3xx', $page->issues);
    }

    public function test_http_refresh_header_recorded(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/r',
            200,
            ['Content-Type' => ['text/html'], 'Refresh' => ['0;url=/x']],
            $this->html(),
            5.0,
        );

        $this->assertContains('internal_http_refresh_redirect', $page->issues);
    }

    public function test_meta_refresh_recorded(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/m',
            200,
            ['Content-Type' => ['text/html']],
            $this->html('<meta http-equiv="refresh" content="0;url=/x">'),
            5.0,
        );

        $this->assertContains('internal_meta_refresh_redirect', $page->issues);
    }
}
