<?php

namespace Tests\Feature\Crawler;

use App\Crawler\SitemapReader;
use App\Jobs\AggregateCrawlJob;
use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HreflangAggregateTest extends TestCase
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

    private function link(Crawl $crawl, CrawlPage $from, string $toUrl): void
    {
        CrawlLink::factory()->create([
            'crawl_id' => $crawl->id,
            'from_page_id' => $from->id,
            'to_url' => $toUrl,
            'type' => 'internal',
        ]);
    }

    public function test_reciprocal_valid_cluster_has_no_cross_page_hreflang_issues(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $en = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'x-default', 'href' => 'https://example.com/en/'],
            ],
        ]);
        $de = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'x-default', 'href' => 'https://example.com/en/'],
            ],
        ]);

        // Both linked so hreflang_unlinked cannot fire either.
        $this->link($crawl, $en, 'https://example.com/de/');
        $this->link($crawl, $de, 'https://example.com/en/');

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $enIssues = $en->refresh()->issues;
        $deIssues = $de->refresh()->issues;

        foreach ([
            'hreflang_non_200', 'hreflang_missing_return_link', 'hreflang_non_canonical_return_link',
            'hreflang_inconsistent_language', 'hreflang_noindex_return_link', 'hreflang_unlinked',
        ] as $code) {
            $this->assertNotContains($code, $enIssues, "en should not have {$code}");
            $this->assertNotContains($code, $deIssues, "de should not have {$code}");
        }
    }

    public function test_missing_return_link(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);
        // B exists but does not reference A back at all.
        $b = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);

        $this->link($crawl, $a, 'https://example.com/de/');
        $this->link($crawl, $b, 'https://example.com/en/');

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_missing_return_link', $a->refresh()->issues);
    }

    public function test_noindex_target_flags_noindex_return_link(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);
        $b = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'status_code' => 200,
            'is_indexable' => false,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
            ],
        ]);

        $this->link($crawl, $a, 'https://example.com/de/');
        $this->link($crawl, $b, 'https://example.com/en/');

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_noindex_return_link', $a->refresh()->issues);
    }

    public function test_non_200_target_status_code(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);
        $b = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'status_code' => 404,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
            ],
        ]);

        $this->link($crawl, $a, 'https://example.com/de/');
        $this->link($crawl, $b, 'https://example.com/en/');

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_non_200', $a->refresh()->issues);
    }

    public function test_non_canonical_return_link(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);
        // B's own canonical points elsewhere, so it disagrees with what A referenced.
        $b = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'canonical' => 'https://example.com/de-alt/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
            ],
        ]);

        $this->link($crawl, $a, 'https://example.com/de/');
        $this->link($crawl, $b, 'https://example.com/en/');

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_non_canonical_return_link', $a->refresh()->issues);
    }

    public function test_inconsistent_language_on_return_entry(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        // A declares itself "en" (self entry) and references B as "de".
        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);
        // B's return entry for A says "fr" instead of "en".
        $b = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'fr', 'href' => 'https://example.com/en/'],
            ],
        ]);

        $this->link($crawl, $a, 'https://example.com/de/');
        $this->link($crawl, $b, 'https://example.com/en/');

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_inconsistent_language', $a->refresh()->issues);
    }

    public function test_unlinked_hreflang_target(): void
    {
        $this->fakeSitemap();
        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
            ],
        ]);
        // B is a valid reciprocal target but has zero <a> inlinks from anywhere.
        $b = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/de/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'de', 'href' => 'https://example.com/de/'],
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
            ],
        ]);

        // No <a> link to B anywhere in the crawl.

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_unlinked', $b->refresh()->issues);
    }

    public function test_off_host_target_faked_non_200(): void
    {
        $this->fakeSitemap();
        // example.de is a resolvable public host; UrlSafety::hostIsSafe() does a real
        // DNS lookup before Http::fake() can intercept, so a non-resolving TLD (.test,
        // .example) would short-circuit the probe to null and never exercise this path.
        Http::fake([
            'example.de/*' => Http::response('', 404),
        ]);

        $crawl = Crawl::factory()->create(['start_url' => 'https://example.com/en/']);

        $a = CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/en/',
            'status_code' => 200,
            'is_indexable' => true,
            'issues' => [],
            'hreflang' => [
                ['lang' => 'en', 'href' => 'https://example.com/en/'],
                ['lang' => 'de', 'href' => 'https://example.de/'],
            ],
        ]);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('hreflang_non_200', $a->refresh()->issues);
    }
}
