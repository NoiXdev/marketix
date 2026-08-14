<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    public function test_stores_security_headers_subset_with_lowercased_keys(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/',
            200,
            ['Content-Type' => ['text/html'], 'Strict-Transport-Security' => ['max-age=63072000'], 'X-Random' => ['ignored']],
            '<!doctype html><html><head><title>t</title></head><body>x</body></html>',
            10.0,
        );

        $this->assertSame('max-age=63072000', $page->security_headers['strict-transport-security']);
        $this->assertArrayNotHasKey('x-random', $page->security_headers);
    }

    public function test_flags_html_served_as_wrong_content_type(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/page',
            200,
            ['Content-Type' => ['text/plain']],
            "\xEF\xBB\xBF<!DOCTYPE html><html><body>hi</body></html>",
            10.0,
        );

        $this->assertContains('wrong_content_type', $page->issues);
    }

    public function test_does_not_flag_a_real_text_html_page(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/ok',
            200,
            ['Content-Type' => ['text/html; charset=utf-8']],
            '<!doctype html><html><head><title>t</title></head><body>hi</body></html>',
            10.0,
        );

        $this->assertNotContains('wrong_content_type', $page->issues);
    }
}
