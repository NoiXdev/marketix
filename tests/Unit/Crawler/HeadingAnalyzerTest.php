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

    public function test_h1_length_and_alt_text_and_stored_h1(): void
    {
        $long = $this->analyze('<h1>'.str_repeat('a', 71).'</h1><h2>ok</h2>');
        $this->assertContains(IssueCode::H1Over70Chars, $long->issues);

        $alt = $this->analyze('<h1><img alt="Logo brand name"></h1><h2>ok</h2>');
        $this->assertContains(IssueCode::AltTextInH1, $alt->issues);

        $stored = $this->analyze('<h1>Real Heading</h1><h2>ok</h2>');
        $this->assertSame('Real Heading', $stored->data['h1']);
    }

    public function test_h2_missing_multiple_duplicate_and_length(): void
    {
        $missing = $this->analyze('<h1>Title</h1><p>no h2 here</p>');
        $this->assertContains(IssueCode::MissingH2, $missing->issues);

        $multi = $this->analyze('<h1>Title</h1><h2>One</h2><h2>Two</h2>');
        $this->assertContains(IssueCode::MultipleH2, $multi->issues);

        $dup = $this->analyze('<h1>Title</h1><h2>Same</h2><h2>same</h2>');
        $this->assertContains(IssueCode::DuplicateH2, $dup->issues);

        $long = $this->analyze('<h1>Title</h1><h2>'.str_repeat('b', 71).'</h2>');
        $this->assertContains(IssueCode::H2Over70Chars, $long->issues);
    }

    public function test_h2_non_sequential_only_before_h1(): void
    {
        $before = $this->analyze('<h2>Early</h2><h1>Title</h1>');
        $this->assertContains(IssueCode::H2NonSequential, $before->issues);

        $after = $this->analyze('<h1>Title</h1><h2>After</h2>');
        $this->assertNotContains(IssueCode::H2NonSequential, $after->issues);
    }
}
