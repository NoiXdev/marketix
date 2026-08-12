<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\HeadingAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class HeadingAnalyzerTest extends TestCase
{
    private function analyze(string $body): AnalyzerResult
    {
        $html = '<html><body>'.$body.'</body></html>';

        return (new HeadingAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));
    }

    public function test_collects_ordered_headings(): void
    {
        $r = $this->analyze('<h1>A</h1><h2>B</h2>');

        $this->assertSame([
            ['level' => 1, 'text' => 'A'],
            ['level' => 2, 'text' => 'B'],
        ], $r->data['headings']);
        $this->assertSame([], $r->issues);
    }

    public function test_flags_missing_h1(): void
    {
        $r = $this->analyze('<h2>B</h2>');
        $this->assertContains(IssueCode::MissingH1, $r->issues);
    }

    public function test_flags_multiple_h1(): void
    {
        $r = $this->analyze('<h1>A</h1><h1>B</h1>');
        $this->assertContains(IssueCode::MultipleH1, $r->issues);
    }

    public function test_flags_heading_order_skip(): void
    {
        $r = $this->analyze('<h1>A</h1><h3>C</h3>');
        $this->assertContains(IssueCode::HeadingOrderSkip, $r->issues);
    }
}
