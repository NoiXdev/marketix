<?php

namespace Tests\Feature\Crawler;

use App\Crawler\LlmsTxtReader;
use App\Crawler\RobotsTxtReader;
use App\Crawler\SafeBrowsershotRenderer;
use App\Crawler\SitemapReader;
use App\Jobs\AggregateCrawlJob;
use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoAggregateTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSitemap(): void
    {
        $this->app->instance(SitemapReader::class, new class extends SitemapReader
        {
            public function urlsFor(string $startUrl): array
            {
                return [];
            }
        });
    }

    /**
     * Builds a fake SafeBrowsershotRenderer that returns fixed HTML without touching a
     * real Browsershot/Chromium. AggregateCrawlJob's startPageJsDependent() helper
     * resolves its renderer through the overridable protected jsRenderer() method
     * (mirroring RunCrawlJob::jsRenderer()) rather than a container binding — using
     * `new`/autowiring there would mean production risks an unconfigured Browsershot
     * instance (missing ->noSandbox()) if ever resolved via the container. So here we
     * subclass AggregateCrawlJob itself and override jsRenderer() to return this fake;
     * production is unaffected since it always uses the real jsRenderer() override.
     */
    private function fakeRenderer(string $html): SafeBrowsershotRenderer
    {
        return new class($html) extends SafeBrowsershotRenderer
        {
            public function __construct(private string $html)
            {
                // Deliberately skip the parent constructor: no real Browsershot needed.
            }

            public function getRenderedHtml(string $url): string
            {
                return $this->html;
            }
        };
    }

    private function runAggregate(Crawl $crawl, ?SafeBrowsershotRenderer $renderer = null): void
    {
        $job = $renderer === null
            ? new AggregateCrawlJob($crawl)
            : new class($crawl, $renderer) extends AggregateCrawlJob
            {
                public function __construct(Crawl $crawl, private SafeBrowsershotRenderer $renderer)
                {
                    parent::__construct($crawl);
                }

                protected function jsRenderer(): SafeBrowsershotRenderer
                {
                    return $this->renderer;
                }
            };

        $job->handle(
            app(SitemapReader::class),
            app(RobotsTxtReader::class),
            app(LlmsTxtReader::class),
        );
    }

    public function test_robots_txt_blocking_gptbot_flags_ai_crawler_blocked(): void
    {
        $this->fakeSitemap();
        $renderer = $this->fakeRenderer('<html><body>'.str_repeat('rendered content ', 50).'</body></html>');

        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: GPTBot\nDisallow: /", 200),
            'example.com/llms.txt' => Http::response('# LLMs\nSome useful content for AI agents.', 200),
            'example.com/' => Http::response('<html><body>'.str_repeat('raw content ', 50).'</body></html>', 200),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/']);

        $start = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/about',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);

        $this->runAggregate($crawl, $renderer);

        $this->assertContains('ai_crawler_blocked', $start->refresh()->issues);
    }

    public function test_missing_llms_txt_flags_start_page(): void
    {
        $this->fakeSitemap();
        $renderer = $this->fakeRenderer('<html><body>'.str_repeat('rendered content ', 50).'</body></html>');

        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
            'example.com/llms.txt' => Http::response('', 404),
            'example.com/' => Http::response('<html><body>'.str_repeat('raw content ', 50).'</body></html>', 200),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/']);

        $start = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/about',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);

        $this->runAggregate($crawl, $renderer);

        $this->assertContains('missing_llms_txt', $start->refresh()->issues);
    }

    public function test_present_llms_txt_does_not_flag_start_page(): void
    {
        $this->fakeSitemap();
        $renderer = $this->fakeRenderer('<html><body>'.str_repeat('rendered content ', 50).'</body></html>');

        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
            'example.com/llms.txt' => Http::response('# LLMs\nSome useful content for AI agents.', 200),
            'example.com/' => Http::response('<html><body>'.str_repeat('raw content ', 50).'</body></html>', 200),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/']);

        $start = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/about',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);

        $this->runAggregate($crawl, $renderer);

        $this->assertNotContains('missing_llms_txt', $start->refresh()->issues);
    }

    public function test_thin_raw_body_vs_rich_render_flags_js_dependent_content(): void
    {
        $this->fakeSitemap();
        // Rich rendered HTML (well over the 200-char / 4x floor) vs. a thin raw body.
        $renderer = $this->fakeRenderer('<html><body>'.str_repeat('rendered content ', 50).'</body></html>');

        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
            'example.com/llms.txt' => Http::response('# LLMs\nSome useful content for AI agents.', 200),
            'example.com/' => Http::response('<html><body>Loading…</body></html>', 200),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/']);

        $start = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/about',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);

        $this->runAggregate($crawl, $renderer);

        $this->assertContains('js_dependent_content', $start->refresh()->issues);
    }

    public function test_sparse_but_fully_rendered_page_does_not_flag_js_dependent_content(): void
    {
        $this->fakeSitemap();
        // Rendered HTML itself has under 200 visible chars — a genuinely sparse but
        // fully server-rendered landing page — and the raw body is essentially the
        // same content (JS added nothing). Without the renderedLen >= 200 floor, the
        // old comparison (raw < max(200, rendered*0.25)) would false-positive here
        // purely because raw fell under the flat 200-char floor.
        $renderer = $this->fakeRenderer('<html><body>Welcome to Acme Inc.</body></html>');

        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
            'example.com/llms.txt' => Http::response('# LLMs\nSome useful content for AI agents.', 200),
            'example.com/' => Http::response('<html><body>Welcome to Acme Inc.</body></html>', 200),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/']);

        $start = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/about',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);

        $this->runAggregate($crawl, $renderer);

        $this->assertNotContains('js_dependent_content', $start->refresh()->issues);
    }

    public function test_rich_raw_body_clears_js_dependent_content_on_start_page(): void
    {
        $this->fakeSitemap();
        $renderer = $this->fakeRenderer('<html><body>'.str_repeat('rendered content ', 50).'</body></html>');

        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
            'example.com/llms.txt' => Http::response('# LLMs\nSome useful content for AI agents.', 200),
            // Raw body is already rich, comparable to the rendered length.
            'example.com/' => Http::response('<html><body>'.str_repeat('raw content ', 50).'</body></html>', 200),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/']);

        // Start page previously flagged js_dependent_content by the per-page heuristic;
        // the site-level diff (raw already rich) must clear it.
        $start = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => ['js_dependent_content'],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/about',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
        ]);

        $this->runAggregate($crawl, $renderer);

        $this->assertNotContains('js_dependent_content', $start->refresh()->issues);
    }
}
