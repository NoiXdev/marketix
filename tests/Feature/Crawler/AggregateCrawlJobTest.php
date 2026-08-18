<?php

namespace Tests\Feature\Crawler;

use App\Crawler\LinkStatusChecker;
use App\Crawler\LlmsTxtReader;
use App\Crawler\RobotsTxtReader;
use App\Crawler\SafeBrowsershotRenderer;
use App\Crawler\SitemapReader;
use App\Enums\CrawlStatus;
use App\Jobs\AggregateCrawlJob;
use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use App\Models\CrawlResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AggregateCrawlJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSitemap(array $urls): void
    {
        $this->app->instance(SitemapReader::class, new class($urls) extends SitemapReader
        {
            public function __construct(private array $urls) {}

            public function urlsFor(string $startUrl): array
            {
                return $this->urls;
            }
        });
    }

    /**
     * These tests predate the site-level GEO pipeline (robots.txt/llms.txt fetch +
     * a raw-vs-rendered JS diff against the crawl's start_url) and aren't about GEO
     * at all, but every AggregateCrawlJob::handle() call now runs it regardless.
     * Several tests here use `https://example.com` as start_url (either explicitly,
     * or via CrawlFactory's default) — a live, resolvable host — so left unfaked,
     * handle() would perform real outbound HTTP and, if the raw fetch ever
     * succeeded, launch a real headless Chromium via the real jsRenderer().
     *
     * This helper keeps every call site hermetic:
     *  - Fakes any example.com request as a 404. Registered here (i.e. after
     *    whatever fake a test already set up earlier in its own body), so a
     *    test's own more-specific fake — e.g. the external-resource-probe fake
     *    in test_checkresources_fills_internal_from_pages_and_probes_external —
     *    still wins for the paths it cares about (first-registered-match-wins).
     *  - Stubs jsRenderer() so Browsershot/Chromium is never invoked, regardless
     *    of what the (now-faked) raw fetch returns.
     * x.test/external.test hosts used elsewhere in this file are untouched: they
     * don't resolve, so those calls already fail fast without reaching a fake or
     * the network (same assumption the rest of this file already relies on).
     */
    private function handleAggregate(Crawl $crawl): void
    {
        Http::fake(function ($request) {
            return str_contains($request->url(), 'example.com')
                ? Http::response('', 404)
                : null;
        });

        $job = new class($crawl) extends AggregateCrawlJob
        {
            protected function jsRenderer(): SafeBrowsershotRenderer
            {
                return new class extends SafeBrowsershotRenderer
                {
                    public function __construct() {}

                    public function getRenderedHtml(string $url): string
                    {
                        return '';
                    }
                };
            }
        };

        $job->handle(app(SitemapReader::class), app(RobotsTxtReader::class), app(LlmsTxtReader::class));
    }

    public function test_computes_inlinks_orphans_and_summary(): void
    {
        $this->fakeSitemap(['https://x.test/']);
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test/']);

        $home = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/', 'title' => 'Home', 'is_indexable' => true, 'issues' => []]);
        $about = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/about', 'title' => 'Home', 'is_indexable' => true, 'issues' => []]);

        // home links to about → about has 1 inlink; nobody links to... about links nowhere
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'to_url' => 'https://x.test/about', 'type' => 'internal']);

        $this->handleAggregate($crawl);

        $about->refresh();
        $home->refresh();
        $crawl->refresh();

        $this->assertSame(1, $about->inlinks_count);
        $this->assertTrue($about->in_sitemap === false);      // /about not in sitemap
        $this->assertFalse($home->is_orphan);                 // start url never orphan
        $this->assertContains('duplicate_title', $about->issues); // both titled "Home"
        $this->assertSame('completed', $crawl->status->value);
        $this->assertArrayHasKey('duplicate_title', $crawl->summary);
    }

    public function test_depth_and_orphan_survive_start_url_redirect(): void
    {
        $this->fakeSitemap([]);
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com']);

        $home = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com',
            'final_url' => 'https://www.example.com/',
            'title' => 'Home',
            'is_indexable' => true,
            'issues' => [],
        ]);
        $sub = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://www.example.com/sub',
            'title' => 'Sub',
            'is_indexable' => true,
            'issues' => [],
        ]);

        CrawlLink::factory()->create([
            'crawl_id' => $crawl->id,
            'from_page_id' => $home->id,
            'to_url' => 'https://www.example.com/sub',
            'type' => 'internal',
        ]);

        $this->handleAggregate($crawl);

        $home->refresh();
        $sub->refresh();

        $this->assertSame(1, $sub->depth);
        $this->assertFalse($home->is_orphan);
    }

    public function test_depth_and_orphan_survive_when_recorded_url_only_matches_via_min_created_fallback(): void
    {
        // Reproduces the bug directly: the home page's recorded `url` itself does not
        // normalize to the crawl's start_url (e.g. spatie recorded the post-redirect
        // form), so the BFS seed can only be resolved via the "earliest created page"
        // fallback rather than a direct url/final_url string match.
        $this->fakeSitemap([]);
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com']);

        $home = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://www.example.com/',
            'title' => 'Home',
            'is_indexable' => true,
            'issues' => [],
            'created_at' => now()->subMinute(),
        ]);
        $sub = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://www.example.com/sub',
            'title' => 'Sub',
            'is_indexable' => true,
            'issues' => [],
        ]);

        CrawlLink::factory()->create([
            'crawl_id' => $crawl->id,
            'from_page_id' => $home->id,
            'to_url' => 'https://www.example.com/sub',
            'type' => 'internal',
        ]);

        $this->handleAggregate($crawl);

        $home->refresh();
        $sub->refresh();

        $this->assertSame(1, $sub->depth);
        $this->assertFalse($home->is_orphan);
    }

    private function fakeLinkChecker(array $statuses): void
    {
        $this->app->instance(LinkStatusChecker::class, new class($statuses) extends LinkStatusChecker
        {
            public function __construct(private array $statuses) {}

            public function status(string $url): ?int
            {
                return $this->statuses[$url] ?? null;
            }
        });
    }

    public function test_flags_pages_that_link_to_broken_urls(): void
    {
        $this->fakeSitemap([]);
        $this->fakeLinkChecker([
            'https://external.test/dead' => 404,
            'https://external.test/ok' => 200,
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test/']);
        $home = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/', 'issues' => []]);
        $other = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/other', 'issues' => []]);

        $deadLink = CrawlLink::factory()->create([
            'crawl_id' => $crawl->id, 'from_page_id' => $home->id,
            'to_url' => 'https://external.test/dead', 'type' => 'external',
        ]);
        CrawlLink::factory()->create([
            'crawl_id' => $crawl->id, 'from_page_id' => $other->id,
            'to_url' => 'https://external.test/ok', 'type' => 'external',
        ]);

        $this->handleAggregate($crawl);

        $home->refresh();
        $other->refresh();
        $deadLink->refresh();
        $crawl->refresh();

        $this->assertContains('broken_link', $home->issues, 'page linking to a 404 should be flagged');
        $this->assertNotContains('broken_link', $other->issues, 'page linking to a 200 must not be flagged');
        $this->assertSame(404, $deadLink->status_code, 'the broken link row should have its status persisted');
        $this->assertSame(1, $crawl->summary['broken_link'] ?? 0);
    }

    public function test_has_no_timeout_so_huge_crawls_can_finish(): void
    {
        $job = new AggregateCrawlJob(Crawl::factory()->create());

        $this->assertSame(0, $job->timeout, 'aggregation must not be killed by a job timeout');
        $this->assertSame(1, $job->tries);
    }

    public function test_failed_hook_marks_crawl_failed(): void
    {
        $crawl = Crawl::factory()->create(['status' => 'running']);

        (new AggregateCrawlJob($crawl))->failed(new \RuntimeException('boom'));

        $crawl->refresh();
        $this->assertSame(CrawlStatus::Failed->value, $crawl->status->value);
        $this->assertStringContainsString('boom', $crawl->error);
        $this->assertNotNull($crawl->finished_at);
    }

    public function test_flags_duplicate_meta_keywords(): void
    {
        $crawl = Crawl::factory()->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/a', 'meta_keywords' => 'shoes, boots', 'issues' => []]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/b', 'meta_keywords' => 'shoes, boots', 'issues' => []]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c', 'meta_keywords' => 'unique kw', 'issues' => []]);

        $this->handleAggregate($crawl);

        $this->assertContains('duplicate_meta_keywords', $a->refresh()->issues);
        $this->assertContains('duplicate_meta_keywords', $b->refresh()->issues);
        $this->assertNotContains('duplicate_meta_keywords', $c->refresh()->issues);
    }

    public function test_flags_duplicate_h1(): void
    {
        $crawl = Crawl::factory()->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/a', 'h1' => 'Welcome', 'issues' => []]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/b', 'h1' => 'Welcome', 'issues' => []]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c', 'h1' => 'Unique Heading', 'issues' => []]);
        // Two pages with no H1 at all (e.g. image-only/empty H1 → null) must never be
        // treated as duplicates of each other: null is not a shared value.
        $d = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/d', 'h1' => null, 'issues' => []]);
        $e = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/e', 'h1' => null, 'issues' => []]);

        $this->handleAggregate($crawl);

        $this->assertContains('duplicate_h1', $a->refresh()->issues);
        $this->assertContains('duplicate_h1', $b->refresh()->issues);
        $this->assertNotContains('duplicate_h1', $c->refresh()->issues);
        $this->assertNotContains('duplicate_h1', $d->refresh()->issues);
        $this->assertNotContains('duplicate_h1', $e->refresh()->issues);
    }

    public function test_flags_cross_page_canonical_and_pagination_issues(): void
    {
        $crawl = Crawl::factory()->create();
        // Canonical → a crawled, non-indexable page.
        $noindex = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/noindex', 'is_indexable' => false, 'status_code' => 200, 'issues' => []]);
        $c1 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c1', 'canonical' => 'https://x.test/noindex', 'issues' => []]);
        // Canonical → an uncrawled URL.
        $c2 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c2', 'canonical' => 'https://x.test/ghost', 'issues' => []]);
        // Pagination next → a 404 crawled page + broken reciprocity.
        $p404 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p2', 'status_code' => 404, 'is_indexable' => true, 'pagination_prev' => 'https://x.test/other', 'issues' => []]);
        $p1 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p1', 'status_code' => 200, 'pagination_next' => 'https://x.test/p2', 'issues' => []]);
        // Pagination next → uncrawled.
        $p3 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p3', 'pagination_next' => 'https://x.test/ghost2', 'issues' => []]);
        // Pagination next → a crawled, non-indexable page (correct reciprocity, so only
        // pagination_non_indexable should fire here, not pagination_sequence_error).
        $p5noindex = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p5-noindex', 'status_code' => 200, 'is_indexable' => false, 'pagination_prev' => 'https://x.test/p5', 'issues' => []]);
        $p5 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p5', 'status_code' => 200, 'pagination_next' => 'https://x.test/p5-noindex', 'issues' => []]);

        $this->handleAggregate($crawl);

        $this->assertContains('non_indexable_canonical', $c1->refresh()->issues);
        $this->assertContains('canonical_not_linked', $c2->refresh()->issues);
        $this->assertContains('pagination_non_200', $p1->refresh()->issues);
        $this->assertContains('pagination_sequence_error', $p1->issues); // p2.prev != p1
        $this->assertContains('pagination_unlinked', $p3->refresh()->issues);
        $this->assertContains('pagination_non_indexable', $p5->refresh()->issues);
        $this->assertNotContains('pagination_sequence_error', $p5->issues); // p5-noindex.prev == p5
    }

    public function test_flags_link_structure_issues(): void
    {
        $crawl = Crawl::factory()->create();
        $noindex = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/noindex', 'content_category' => 'html', 'is_indexable' => false, 'issues' => []]);
        $source = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/source', 'content_category' => 'html', 'is_indexable' => true, 'issues' => []]);
        $target = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/target', 'content_category' => 'html', 'is_indexable' => true, 'issues' => []]);
        $image = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/logo.png', 'content_category' => 'image', 'is_indexable' => false, 'issues' => []]);

        // source → nofollow internal to target, localhost link, internal to a noindex page.
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $source->id, 'to_url' => 'https://x.test/target', 'type' => 'internal', 'rel' => 'nofollow', 'anchor' => 'x']);
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $source->id, 'to_url' => 'http://localhost/x', 'type' => 'external', 'rel' => null, 'anchor' => 'x']);
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $source->id, 'to_url' => 'https://x.test/noindex', 'type' => 'internal', 'rel' => null, 'anchor' => 'x']);
        // noindex page → target (follow), making target's only follow-inlink source non-indexable.
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $noindex->id, 'to_url' => 'https://x.test/target', 'type' => 'internal', 'rel' => null, 'anchor' => 'x']);

        $this->handleAggregate($crawl);

        $s = $source->refresh()->issues;
        $this->assertContains('internal_nofollow_outlinks', $s);
        $this->assertContains('outlinks_to_localhost', $s);
        $this->assertContains('pages_non_crawlable_internal_outlinks', $s);

        $t = $target->refresh()->issues;
        // target has 2 internal inlinks: one nofollow (source), one follow (noindex) → both follow+nofollow present.
        $this->assertContains('follow_nofollow_internal_inlinks', $t);

        // The image resource must NOT get a dead-end outlink flag.
        $this->assertNotContains('pages_no_internal_outlinks', $image->refresh()->issues);
    }

    public function test_flags_exact_duplicates(): void
    {
        $crawl = Crawl::factory()->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/a', 'content_hash' => 'abc123', 'issues' => []]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/b', 'content_hash' => 'abc123', 'issues' => []]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c', 'content_hash' => 'unique-hash', 'issues' => []]);

        $this->handleAggregate($crawl);

        $this->assertContains('exact_duplicates', $a->refresh()->issues);
        $this->assertContains('exact_duplicates', $b->refresh()->issues);
        $this->assertNotContains('exact_duplicates', $c->refresh()->issues);
    }

    public function test_checkresources_fills_internal_from_pages_and_probes_external(): void
    {
        // cdn.test doesn't resolve, and UrlSafety::hostIsSafe() does a real DNS lookup
        // before Http::fake() can intercept — so the external resource here must use a
        // resolvable, public host (example.com). The internal resource stays on x.test
        // since it's resolved via the crawled-page map and never probed.
        Http::fake(['example.com/*' => Http::response('', 200, ['Content-Length' => '5000'])]);

        $crawl = Crawl::factory()->create();
        $home = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/', 'issues' => []]);
        // A crawled CSS page (internal resource resolves here — note trailing-slash normalisation).
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/app.css', 'status_code' => 200, 'size_bytes' => 4096, 'content_category' => 'css', 'issues' => []]);

        $internal = CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'url' => 'https://x.test/app.css/', 'type' => 'css', 'is_internal' => true]);
        $external = CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'url' => 'https://example.com/lib.js', 'type' => 'javascript', 'is_internal' => false]);
        $uncrawled = CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'url' => 'https://x.test/missing.css', 'type' => 'css', 'is_internal' => true]);

        $this->handleAggregate($crawl);

        $this->assertSame(200, $internal->refresh()->status_code);
        $this->assertSame(4096, $internal->size_bytes); // matched via trailing-slash-normalised URL
        $this->assertSame(200, $external->refresh()->status_code);
        $this->assertSame(5000, $external->size_bytes);
        $this->assertNull($uncrawled->refresh()->status_code); // internal but never crawled
    }
}
