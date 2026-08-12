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

    public function test_non_html_resource_skips_html_checks_and_flags_oversize(): void
    {
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test']);
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'x.test');

        // A 400 KB SVG — classifies as an image, over the 300 KB "large" threshold.
        $body = str_repeat('a', 400 * 1024);
        $page = $observer->recordResponse(
            'https://x.test/logo.svg', 200, ['Content-Type' => ['image/svg+xml']], $body, 5.0
        );

        $this->assertSame('image', $page->content_category);
        $this->assertSame(strlen($body), $page->size_bytes);
        $this->assertFalse($page->is_indexable);
        $this->assertContains('large_resource', $page->issues);

        // None of the nonsensical HTML/SEO issues should be present for a binary file.
        foreach (['missing_title', 'missing_h1', 'thin_content', 'missing_meta_description', 'missing_structured_data'] as $htmlIssue) {
            $this->assertNotContains($htmlIssue, $page->issues, "unexpected HTML issue on a non-HTML resource: {$htmlIssue}");
        }

        // And it recorded no headings/title.
        $this->assertNull($page->title);
        $this->assertNull($page->headings);
    }

    public function test_small_non_html_resource_has_no_issues(): void
    {
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test']);
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'x.test');

        $page = $observer->recordResponse(
            'https://x.test/icon.png', 200, ['Content-Type' => ['image/png']], str_repeat('a', 5 * 1024), 5.0
        );

        $this->assertSame('image', $page->content_category);
        $this->assertSame([], $page->issues);
    }
}
