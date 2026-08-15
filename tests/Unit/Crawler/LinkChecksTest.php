<?php

namespace Tests\Unit\Crawler;

use App\Crawler\LinkChecks;
use PHPUnit\Framework\TestCase;

class LinkChecksTest extends TestCase
{
    private function stats(array $over = []): array
    {
        return array_merge([
            'internal' => 5, 'external' => 5, 'nofollow_internal' => false,
            'no_anchor_internal' => false, 'non_descriptive_internal' => false,
            'localhost' => false, 'non_crawlable_internal' => false,
        ], $over);
    }

    public function test_is_non_descriptive(): void
    {
        $this->assertTrue(LinkChecks::isNonDescriptive('Click Here'));
        $this->assertTrue(LinkChecks::isNonDescriptive('read more'));
        $this->assertFalse(LinkChecks::isNonDescriptive('Our pricing plans'));
    }

    public function test_is_localhost(): void
    {
        $this->assertTrue(LinkChecks::isLocalhost('http://localhost/x'));
        $this->assertTrue(LinkChecks::isLocalhost('http://127.0.0.1:8080/'));
        $this->assertTrue(LinkChecks::isLocalhost('http://[::1]/'));
        $this->assertFalse(LinkChecks::isLocalhost('https://example.com/'));
    }

    public function test_depth_and_count_thresholds(): void
    {
        $this->assertNotContains('pages_high_crawl_depth', LinkChecks::outlinkIssues($this->stats(), 3));
        $this->assertContains('pages_high_crawl_depth', LinkChecks::outlinkIssues($this->stats(), 4));
        $this->assertNotContains('pages_many_internal_outlinks', LinkChecks::outlinkIssues($this->stats(['internal' => 100]), 0));
        $this->assertContains('pages_many_internal_outlinks', LinkChecks::outlinkIssues($this->stats(['internal' => 101]), 0));
        $this->assertContains('pages_many_external_outlinks', LinkChecks::outlinkIssues($this->stats(['external' => 101]), 0));
    }

    public function test_no_internal_outlinks_and_flags(): void
    {
        $this->assertContains('pages_no_internal_outlinks', LinkChecks::outlinkIssues($this->stats(['internal' => 0]), 0));
        $codes = LinkChecks::outlinkIssues($this->stats([
            'nofollow_internal' => true, 'no_anchor_internal' => true,
            'non_descriptive_internal' => true, 'localhost' => true, 'non_crawlable_internal' => true,
        ]), 0);
        foreach (['internal_nofollow_outlinks', 'internal_outlinks_no_anchor', 'non_descriptive_anchor_internal_outlinks', 'outlinks_to_localhost', 'pages_non_crawlable_internal_outlinks'] as $c) {
            $this->assertContains($c, $codes);
        }
    }

    public function test_inlink_issues(): void
    {
        $this->assertSame([], LinkChecks::inlinkIssues(['count' => 0, 'follow' => false, 'nofollow' => false, 'anyIndexableSource' => false]));
        $this->assertContains('follow_nofollow_internal_inlinks', LinkChecks::inlinkIssues(['count' => 2, 'follow' => true, 'nofollow' => true, 'anyIndexableSource' => true]));
        $this->assertContains('only_internal_nofollow_inlinks', LinkChecks::inlinkIssues(['count' => 1, 'follow' => false, 'nofollow' => true, 'anyIndexableSource' => true]));
        $this->assertContains('only_non_indexable_inlinks', LinkChecks::inlinkIssues(['count' => 1, 'follow' => true, 'nofollow' => false, 'anyIndexableSource' => false]));
        // A clean follow-only inlink from an indexable source → nothing.
        $this->assertSame([], LinkChecks::inlinkIssues(['count' => 1, 'follow' => true, 'nofollow' => false, 'anyIndexableSource' => true]));
    }
}
