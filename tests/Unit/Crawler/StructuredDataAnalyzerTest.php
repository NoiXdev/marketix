<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\StructuredDataAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class StructuredDataAnalyzerTest extends TestCase
{
    public function test_extracts_types_from_jsonld(): void
    {
        $html = '<html><head>'
            .'<script type="application/ld+json">{"@type":"Organization","name":"X"}</script>'
            .'<script type="application/ld+json">{"@graph":[{"@type":"WebPage"},{"@type":"BreadcrumbList"}]}</script>'
            .'</head><body>x</body></html>';

        $r = (new StructuredDataAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertEqualsCanonicalizing(['Organization', 'WebPage', 'BreadcrumbList'], $r->data['structured_data']);
        $this->assertSame([], $r->issues);
    }

    public function test_flags_missing_structured_data(): void
    {
        $r = (new StructuredDataAnalyzer)->analyze(new Crawler('<html><body>x</body></html>'), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertSame([], $r->data['structured_data']);
        $this->assertContains(IssueCode::MissingStructuredData, $r->issues);
    }
}
