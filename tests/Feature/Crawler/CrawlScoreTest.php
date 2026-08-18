<?php

namespace Tests\Feature\Crawler;

use App\Crawler\CheckCatalog;
use App\Crawler\CrawlScore;
use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_crawl_gives_perfect_scores(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 3]);
        CrawlPage::factory()->for($crawl)->count(3)->create(['issues' => []]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['overall']);
        $this->assertSame(100, $result['seo']);
        $this->assertSame(100, $result['geo']);
    }

    public function test_single_page_single_notice_scores_exactly_97(): void
    {
        // Per-page budget is a fixed constant (18); one notice (weight 0.5) on the
        // only page: health = 1 - 0.5/18 = 17.5/18 -> round(100 * 17.5/18) = 97.
        $crawl = Crawl::factory()->create(['pages_crawled' => 1]);
        CrawlPage::factory()->for($crawl)->create(['issues' => ['thin_content']]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(97, $result['overall']);
    }

    public function test_every_active_problem_check_failing_on_the_only_page_tanks_every_score(): void
    {
        // A single-page crawl where every active, non-info check fails drives the per-page
        // penalty for every set of categories (overall, seo, geo) far past that set's budget,
        // so every score bottoms out at 0.
        $crawl = Crawl::factory()->create(['pages_crawled' => 1]);
        CrawlPage::factory()->for($crawl)->create(['issues' => CheckCatalog::activeCodes()]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(0, $result['overall']);
        $this->assertSame(0, $result['seo']);
        $this->assertSame(0, $result['geo']);
    }

    public function test_info_codes_do_not_affect_any_score_or_top_actions(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 5]);
        CrawlPage::factory()->for($crawl)->count(5)->create([
            'issues' => ['https_urls', 'missing_meta_keywords'],
        ]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['overall']);
        $this->assertSame(100, $result['seo']);
        $this->assertSame(100, $result['geo']);

        $codes = array_column($result['topActions'], 'code');
        $this->assertNotContains('https_urls', $codes);
        $this->assertNotContains('missing_meta_keywords', $codes);
    }

    public function test_geo_only_problem_dips_geo_below_seo_and_below_a_hundred(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 10]);
        CrawlPage::factory()->for($crawl)->count(4)->create(['issues' => ['no_semantic_html']]);
        CrawlPage::factory()->for($crawl)->count(6)->create(['issues' => []]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['seo']);
        $this->assertLessThan(100, $result['geo']);
        $this->assertLessThan($result['seo'], $result['geo']);
    }

    public function test_seo_only_problem_dips_seo_but_leaves_geo_perfect(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 10]);
        CrawlPage::factory()->for($crawl)->count(10)->create(['issues' => ['missing_title']]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['geo']);
        $this->assertLessThan(100, $result['seo']);
    }

    public function test_missing_meta_keywords_never_counts_as_a_problem(): void
    {
        $this->assertFalse(CheckCatalog::isProblemCode('missing_meta_keywords'));

        $crawl = Crawl::factory()->create(['pages_crawled' => 1]);
        CrawlPage::factory()->for($crawl)->create(['issues' => ['missing_meta_keywords']]);

        $result = CrawlScore::for($crawl);

        $this->assertSame(100, $result['overall']);
        $this->assertSame(100, $result['seo']);
        $this->assertSame(100, $result['geo']);
        $this->assertSame([], $result['topActions']);
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
            // info: must never appear regardless of how high its count is
            'missing_meta_keywords' => 10,
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

        $codes = array_column($result['topActions'], 'code');
        $this->assertNotContains('missing_meta_keywords', $codes);
    }

    public function test_all_scores_are_ints_between_zero_and_a_hundred(): void
    {
        $crawl = Crawl::factory()->create(['pages_crawled' => 7]);
        CrawlPage::factory()->for($crawl)->count(3)->create(['issues' => ['missing_title', 'no_semantic_html']]);
        CrawlPage::factory()->for($crawl)->count(4)->create(['issues' => []]);

        $result = CrawlScore::for($crawl);

        foreach (['overall', 'seo', 'geo'] as $key) {
            $this->assertIsInt($result[$key], $key);
            $this->assertGreaterThanOrEqual(0, $result[$key], $key);
            $this->assertLessThanOrEqual(100, $result[$key], $key);
        }

        foreach ($result['categories'] as $category => $score) {
            $this->assertIsInt($score, $category);
            $this->assertGreaterThanOrEqual(0, $score, $category);
            $this->assertLessThanOrEqual(100, $score, $category);
        }
    }
}
