<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\IndexabilityAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class IndexabilityAnalyzerTest extends TestCase
{
    private function analyze(string $head, PageContext $ctx): AnalyzerResult
    {
        $html = '<html><head>'.$head.'</head><body>x</body></html>';

        return (new IndexabilityAnalyzer)->analyze(new Crawler($html), $ctx);
    }

    public function test_noindex_marks_not_indexable(): void
    {
        $r = $this->analyze('<meta name="robots" content="noindex,follow">', new PageContext('https://x.test/p', 200, 'x.test'));

        $this->assertFalse($r->data['is_indexable']);
        $this->assertSame('noindex', $r->data['indexability_reason']);
        $this->assertContains(IssueCode::Noindex, $r->issues);
    }

    public function test_robots_block_wins_over_everything(): void
    {
        $r = $this->analyze('<meta name="robots" content="noindex">', new PageContext('https://x.test/p', 200, 'x.test', robotsBlocked: true));

        $this->assertFalse($r->data['is_indexable']);
        $this->assertSame('robots_blocked', $r->data['indexability_reason']);
        $this->assertContains(IssueCode::RobotsBlocked, $r->issues);
    }

    public function test_canonical_mismatch_is_flagged_but_still_indexable(): void
    {
        $r = $this->analyze('<link rel="canonical" href="https://x.test/other">', new PageContext('https://x.test/p', 200, 'x.test'));

        $this->assertTrue($r->data['is_indexable']);
        $this->assertContains(IssueCode::CanonicalMismatch, $r->issues);
    }

    public function test_plain_page_is_indexable(): void
    {
        $r = $this->analyze('', new PageContext('https://x.test/p', 200, 'x.test'));

        $this->assertTrue($r->data['is_indexable']);
        $this->assertNull($r->data['indexability_reason']);
        $this->assertSame([], $r->issues);
    }
}
