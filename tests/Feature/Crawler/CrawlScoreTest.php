<?php

namespace Tests\Feature\Crawler;

use App\Crawler\CrawlScore;
use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_issues_gives_perfect_scores(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 3]);
        CrawlPage::factory()->for($crawl)->count(3)->create(['issues' => []]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['overall']);
        $this->assertSame(100, $result['seo']);
        $this->assertSame(100, $result['geo']);
    }

    public function test_error_on_every_page_tanks_overall_and_its_category(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 4]);
        CrawlPage::factory()->for($crawl)->count(4)->create(['issues' => ['missing_title']]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(0, $result['overall']);
        $this->assertSame(0, $result['categories']['page_title']);
        // page_title is not geo, so it also drags seo down while geo stays clean.
        $this->assertSame(0, $result['seo']);
        $this->assertSame(100, $result['geo']);
    }

    public function test_geo_issue_dips_geo_but_not_seo(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 10]);
        CrawlPage::factory()->for($crawl)->count(2)->create(['issues' => ['no_semantic_html']]);
        CrawlPage::factory()->for($crawl)->count(8)->create(['issues' => []]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['seo']);
        $this->assertLessThan(100, $result['geo']);
        $this->assertSame(93, $result['geo']);
    }

    public function test_seo_issue_dips_seo_but_not_geo(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 10]);
        CrawlPage::factory()->for($crawl)->count(2)->create(['issues' => ['missing_title']]);
        CrawlPage::factory()->for($crawl)->count(8)->create(['issues' => []]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['geo']);
        $this->assertLessThan(100, $result['seo']);
        $this->assertSame(80, $result['seo']);
    }

    public function test_info_code_does_not_affect_any_score(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 5]);
        CrawlPage::factory()->for($crawl)->count(5)->create(['issues' => ['https_urls']]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['overall']);
        $this->assertSame(100, $result['seo']);
        $this->assertSame(100, $result['geo']);
    }

    public function test_top_actions_sorted_by_severity_then_count_and_capped_at_ten(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 10]);

        // code => number of pages (out of 10) carrying that code.
        $counts = [
            // errors
            'missing_title' => 6,
            'missing_h1' => 4,
            'client_error' => 2,
            // warnings
            'duplicate_title' => 9,
            'robots_blocked' => 7,
            'redirect_chain' => 5,
            'canonical_mismatch' => 3,
            'orphan_page' => 1,
            // notices (only the top 2 by count should survive the cap)
            'title_too_long' => 10,
            'missing_alt_text' => 8,
            'missing_meta_keywords' => 3,
            'not_in_sitemap' => 1,
        ];

        for ($i = 0; $i < 10; $i++) {
            $issues = [];
            foreach ($counts as $code => $count) {
                if ($i < $count) {
                    $issues[] = $code;
                }
            }
            CrawlPage::factory()->for($crawl)->create(['issues' => $issues]);
        }

        $result = CrawlScore::for($crawl);

        $this->assertCount(10, $result['topActions']);

        $expected = [
            ['code' => 'missing_title', 'category' => 'page_title', 'severity' => 'error', 'count' => 6],
            ['code' => 'missing_h1', 'category' => 'h1', 'severity' => 'error', 'count' => 4],
            ['code' => 'client_error', 'category' => 'response_codes', 'severity' => 'error', 'count' => 2],
            ['code' => 'duplicate_title', 'category' => 'page_title', 'severity' => 'warning', 'count' => 9],
            ['code' => 'robots_blocked', 'category' => 'response_codes', 'severity' => 'warning', 'count' => 7],
            ['code' => 'redirect_chain', 'category' => 'response_codes', 'severity' => 'warning', 'count' => 5],
            ['code' => 'canonical_mismatch', 'category' => 'canonicals', 'severity' => 'warning', 'count' => 3],
            ['code' => 'orphan_page', 'category' => 'links', 'severity' => 'warning', 'count' => 1],
            ['code' => 'title_too_long', 'category' => 'page_title', 'severity' => 'notice', 'count' => 10],
            ['code' => 'missing_alt_text', 'category' => 'images', 'severity' => 'notice', 'count' => 8],
        ];

        $this->assertSame($expected, $result['topActions']);
    }
}
