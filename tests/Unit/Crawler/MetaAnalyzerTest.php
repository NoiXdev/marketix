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
        // 'Hello World' / 'A page' are short by the new length+pixel checks, and this
        // fixture has no meta keywords tag, so those notice-level issues now fire too.
        $this->assertSame([
            IssueCode::TitleBelow30Chars,
            IssueCode::TitleBelow200px,
            IssueCode::MetaDescriptionBelow70Chars,
            IssueCode::MetaDescriptionBelow400px,
            IssueCode::MissingMetaKeywords,
        ], $r->issues);
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

    public function test_title_length_and_pixel_and_same_as_h1(): void
    {
        // Short title (< 30 chars, < 200px) that also equals the h1.
        $r = $this->analyze('<html><head><title>Home</title></head><body><h1>Home</h1>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::TitleBelow30Chars, $r->issues);
        $this->assertContains(IssueCode::TitleBelow200px, $r->issues);
        $this->assertContains(IssueCode::TitleSameAsH1, $r->issues);

        // Very long title (> 561px).
        $long = $this->analyze('<html><head><title>'.str_repeat('a', 70).'</title></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::TitleOver561px, $long->issues);
    }

    public function test_structural_title_checks_and_svg_is_ignored(): void
    {
        $two = $this->analyze('<html><head><title>One</title></head><body><title>Two</title>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MultipleTitle, $two->issues);
        $this->assertContains(IssueCode::TitleOutsideHead, $two->issues);

        // An SVG <title> must NOT count as a page title.
        $svg = $this->analyze('<html><head><title>Only</title></head><body><svg><title>icon</title></svg>'.str_repeat('w ', 150).'</body></html>');
        $this->assertNotContains(IssueCode::MultipleTitle, $svg->issues);
        $this->assertNotContains(IssueCode::TitleOutsideHead, $svg->issues);
    }

    public function test_description_length_and_structural(): void
    {
        $short = $this->analyze('<html><head><title>A normal length title here</title><meta name="description" content="Too short."></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MetaDescriptionBelow70Chars, $short->issues);
        $this->assertContains(IssueCode::MetaDescriptionBelow400px, $short->issues);

        $two = $this->analyze('<html><head><title>A normal length title here</title><meta name="description" content="one"><meta name="description" content="two"></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MultipleMetaDescription, $two->issues);
    }

    public function test_meta_keywords_extraction_and_checks(): void
    {
        $none = $this->analyze('<html><head><title>A normal length title here</title></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MissingMetaKeywords, $none->issues);
        $this->assertNull($none->data['meta_keywords']);

        $kw = $this->analyze('<html><head><title>A normal length title here</title><meta name="keywords" content="a, b"><meta name="keywords" content="c"></head><body>'.str_repeat('w ', 150).'</body></html>');
        $this->assertContains(IssueCode::MultipleMetaKeywords, $kw->issues);
        $this->assertSame('a, b', $kw->data['meta_keywords']);
    }
}
