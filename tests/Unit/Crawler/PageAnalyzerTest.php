<?php

namespace Tests\Unit\Crawler;

use App\Crawler\IssueCode;
use App\Crawler\PageAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;

class PageAnalyzerTest extends TestCase
{
    public function test_merges_data_and_issues_from_all_analyzers(): void
    {
        $html = '<html><head><title>T</title></head><body><h2>only h2</h2><img src="/a.png"></body></html>';
        $r = (new PageAnalyzer)->analyze($html, new PageContext('https://x.test/p', 200, 'x.test'));

        // meta data present
        $this->assertArrayHasKey('title', $r->data);
        // heading + image issues both bubbled up
        $this->assertContains(IssueCode::MissingH1, $r->issues);
        $this->assertContains(IssueCode::MissingAltText, $r->issues);
        // issues are deduplicated (no duplicate enum values)
        $values = array_map(fn ($i) => $i->value, $r->issues);
        $this->assertSame($values, array_values(array_unique($values)));
    }
}
