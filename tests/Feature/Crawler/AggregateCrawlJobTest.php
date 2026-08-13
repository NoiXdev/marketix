<?php

namespace Tests\Feature\Crawler;

use App\Crawler\LinkStatusChecker;
use App\Crawler\SitemapReader;
use App\Enums\CrawlStatus;
use App\Jobs\AggregateCrawlJob;
use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_computes_inlinks_orphans_and_summary(): void
    {
        $this->fakeSitemap(['https://x.test/']);
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test/']);

        $home = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/', 'title' => 'Home', 'is_indexable' => true, 'issues' => []]);
        $about = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/about', 'title' => 'Home', 'is_indexable' => true, 'issues' => []]);

        // home links to about → about has 1 inlink; nobody links to... about links nowhere
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'to_url' => 'https://x.test/about', 'type' => 'internal']);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

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

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

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

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

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

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

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
}
