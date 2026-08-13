<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Crawler\SafeBrowsershotRenderer;
use App\Crawler\SitemapReader;
use App\Enums\CrawlStatus;
use App\Jobs\AggregateCrawlJob;
use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Browsershot\Browsershot;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\JavaScriptRenderers\JavaScriptRenderer;
use Tests\TestCase;

class RunCrawlJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_hook_marks_crawl_failed(): void
    {
        $crawl = Crawl::factory()->create(['status' => 'running']);

        (new RunCrawlJob($crawl))->failed(new \RuntimeException('boom'));

        $crawl->refresh();
        $this->assertSame(CrawlStatus::Failed->value, $crawl->status->value);
        $this->assertStringContainsString('boom', $crawl->error);
    }

    public function test_single_page_crawl_records_page_and_dispatches_aggregate(): void
    {
        Bus::fake([AggregateCrawlJob::class]);

        $crawl = Crawl::factory()->create([
            'start_url' => 'https://example.test/',
            'mode' => 'single_page',
            'respect_robots' => false,
        ]);

        $html = '<html><head><title>Fx</title></head><body><h1>Fx</h1>'
            .str_repeat('word ', 150).'</body></html>';

        // Subclass injects spatie's fake() into the configured crawler so the whole
        // pipeline (config -> observer -> persistence) runs without live HTTP.
        $job = new class($crawl, $html) extends RunCrawlJob
        {
            public function __construct(Crawl $crawl, private string $html)
            {
                parent::__construct($crawl);
            }

            protected function buildCrawler(CrawlPageObserver $observer): Crawler
            {
                return parent::buildCrawler($observer)->fake([$this->crawl->start_url => $this->html]);
            }
        };

        $job->handle();

        $crawl->refresh();
        $this->assertGreaterThanOrEqual(1, $crawl->pages_crawled);
        Bus::assertDispatched(AggregateCrawlJob::class);
    }

    public function test_js_render_uses_a_no_sandbox_browsershot(): void
    {
        // Chromium cannot launch in a container/root environment without --no-sandbox;
        // the JS renderer must configure Browsershot accordingly, otherwise every
        // JS-rendered fetch fails and the crawl returns only the (failed) root page.
        $crawl = Crawl::factory()->create(['render_js' => true]);

        $job = new class($crawl) extends RunCrawlJob
        {
            public function exposeRenderer(): JavaScriptRenderer
            {
                return $this->jsRenderer();
            }
        };

        $renderer = $job->exposeRenderer();
        $this->assertInstanceOf(SafeBrowsershotRenderer::class, $renderer);

        $browsershot = $this->readProtected($renderer, 'browsershot');
        $this->assertInstanceOf(Browsershot::class, $browsershot);
        $this->assertTrue($this->readProtected($browsershot, 'noSandbox'), 'Browsershot must run with --no-sandbox');
    }

    public function test_js_renderer_encodes_spaces_and_swallows_render_failures(): void
    {
        // A single un-renderable URL (e.g. one with a raw space/umlaut) must NOT abort
        // the crawl: spatie only catches ProcessFailedException, so Browsershot's
        // FileUrlNotAllowed would otherwise bubble up and fail the whole scan.
        $spy = new class extends Browsershot
        {
            public ?string $received = null;

            public function setUrl(string $url): static
            {
                $this->received = $url;

                throw new \RuntimeException('boom'); // stand-in for FileUrlNotAllowed / any render failure
            }
        };

        $renderer = new SafeBrowsershotRenderer($spy);
        $result = $renderer->getRenderedHtml('https://media.i-do.app/x/2026_07_03 datenschutz.pdf');

        $this->assertSame('', $result, 'a render failure must return empty, not throw');
        $this->assertSame('https://media.i-do.app/x/2026_07_03%20datenschutz.pdf', $spy->received, 'spaces must be encoded');
    }

    public function test_js_renderer_refuses_to_point_the_browser_at_an_internal_host(): void
    {
        // SSRF defence in depth: a private/internal host must never reach the browser
        // (e.g. via DNS rebinding of the crawled domain).
        $spy = new class extends Browsershot
        {
            public bool $setUrlCalled = false;

            public function setUrl(string $url): static
            {
                $this->setUrlCalled = true;

                return $this;
            }
        };

        $renderer = new SafeBrowsershotRenderer($spy);

        $this->assertSame('', $renderer->getRenderedHtml('http://127.0.0.1/admin'));
        $this->assertFalse($spy->setUrlCalled, 'the browser must never be pointed at a private host');
    }

    public function test_sitemap_urls_are_seeded_into_the_queue_when_enabled(): void
    {
        $crawl = Crawl::factory()->create([
            'start_url' => 'https://example.test/',
            'mode' => 'full_site',
            'crawl_sitemap' => true,
            'respect_robots' => false,
        ]);

        // Fake the sitemap: one same-host URL (should be seeded) and one external
        // URL (must be rejected — the crawl stays on the start domain).
        $this->app->instance(SitemapReader::class, new class extends SitemapReader
        {
            public function urlsFor(string $startUrl): array
            {
                return ['https://example.test/orphan-only-in-sitemap', 'https://evil.test/x'];
            }
        });

        $job = new class($crawl) extends RunCrawlJob
        {
            public function seedFor(CrawlPageObserver $observer): Crawler
            {
                $crawler = $this->buildCrawler($observer);
                $this->seedSitemapUrls($crawler);

                return $crawler;
            }
        };

        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'example.test');
        $crawler = $job->seedFor($observer);

        $this->assertTrue($crawler->getCrawlQueue()->has('https://example.test/orphan-only-in-sitemap'));
        $this->assertFalse($crawler->getCrawlQueue()->has('https://evil.test/x'));
    }

    public function test_sitemap_urls_are_not_seeded_when_disabled(): void
    {
        $crawl = Crawl::factory()->create([
            'start_url' => 'https://example.test/',
            'mode' => 'full_site',
            'crawl_sitemap' => false,
        ]);

        $this->app->instance(SitemapReader::class, new class extends SitemapReader
        {
            public function urlsFor(string $startUrl): array
            {
                return ['https://example.test/orphan-only-in-sitemap'];
            }
        });

        $job = new class($crawl) extends RunCrawlJob
        {
            public function seedFor(CrawlPageObserver $observer): Crawler
            {
                $crawler = $this->buildCrawler($observer);
                $this->seedSitemapUrls($crawler);

                return $crawler;
            }
        };

        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'example.test');
        $crawler = $job->seedFor($observer);

        $this->assertFalse($crawler->getCrawlQueue()->has('https://example.test/orphan-only-in-sitemap'));
    }

    private function readProtected(object $object, string $property): mixed
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }
}
