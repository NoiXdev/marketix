<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\MetaAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class MetaAnalyzerTest extends TestCase
{
    private function analyze(string $html): AnalyzerResult
    {
        return (new MetaAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));
    }

    public function test_extracts_title_description_canonical(): void
    {
        $r = $this->analyze('<html><head><title>Hello World</title>'
            .'<meta name="description" content="A page">'
            .'<link rel="canonical" href="https://x.test/">'
            .'<meta name="robots" content="index,follow"></head>'
            .'<body>'.str_repeat('word ', 150).'</body></html>');

        $this->assertSame('Hello World', $r->data['title']);
        $this->assertSame(11, $r->data['title_length']);
        $this->assertSame('A page', $r->data['meta_description']);
        $this->assertSame('https://x.test/', $r->data['canonical']);
        $this->assertSame('index,follow', $r->data['meta_robots']);
        $this->assertGreaterThanOrEqual(150, $r->data['word_count']);
        $this->assertSame([], $r->issues);
    }

    public function test_flags_missing_title_and_description_and_thin_content(): void
    {
        $r = $this->analyze('<html><head></head><body>short</body></html>');

        $this->assertContains(IssueCode::MissingTitle, $r->issues);
        $this->assertContains(IssueCode::MissingMetaDescription, $r->issues);
        $this->assertContains(IssueCode::ThinContent, $r->issues);
    }

    public function test_flags_overlong_title(): void
    {
        $r = $this->analyze('<html><head><title>'.str_repeat('a', 61).'</title></head><body>'.str_repeat('w ', 150).'</body></html>');

        $this->assertContains(IssueCode::TitleTooLong, $r->issues);
    }
}
