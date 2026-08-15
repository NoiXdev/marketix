<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\PaginationAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class PaginationAnalyzerTest extends TestCase
{
    private function analyze(string $head, string $body = '', string $url = 'https://x.test/page-2'): AnalyzerResult
    {
        $html = "<html><head>{$head}</head><body>{$body}</body></html>";

        return (new PaginationAnalyzer)->analyze(new Crawler($html), new PageContext($url, 200, 'x.test'));
    }

    /** @return string[] */
    private function codes(AnalyzerResult $r): array
    {
        return array_map(fn ($i) => $i->value, $r->issues);
    }

    public function test_none(): void
    {
        $r = $this->analyze('');
        $this->assertSame([], $r->issues);
        $this->assertNull($r->data['pagination_next']);
    }

    public function test_first_page_and_stores_next(): void
    {
        $r = $this->analyze('<link rel="next" href="https://x.test/page-2">', '<a href="https://x.test/page-2">next</a>', 'https://x.test/page-1');
        $codes = $this->codes($r);
        $this->assertContains('has_pagination', $codes);
        $this->assertContains('pagination_first_page', $codes);
        $this->assertSame('https://x.test/page-2', $r->data['pagination_next']);
    }

    public function test_paginated_2plus_and_multiple(): void
    {
        $codes = $this->codes($this->analyze('<link rel="prev" href="https://x.test/p1"><link rel="next" href="https://x.test/p3"><link rel="next" href="https://x.test/p4">', '<a href="https://x.test/p1">a</a><a href="https://x.test/p3">b</a><a href="https://x.test/p4">c</a>'));
        $this->assertContains('paginated_2plus', $codes);
        $this->assertContains('multiple_pagination_urls', $codes);
    }

    public function test_url_not_in_anchor(): void
    {
        // next link present but no matching <a>.
        $codes = $this->codes($this->analyze('<link rel="next" href="https://x.test/page-3">'));
        $this->assertContains('pagination_url_not_in_anchor', $codes);
    }

    public function test_loop(): void
    {
        $codes = $this->codes($this->analyze('<link rel="next" href="https://x.test/page-2">', '<a href="https://x.test/page-2">a</a>', 'https://x.test/page-2'));
        $this->assertContains('pagination_loop', $codes);
    }
}
