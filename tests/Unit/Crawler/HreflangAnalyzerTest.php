<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\HreflangAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class HreflangAnalyzerTest extends TestCase
{
    private function runAnalyzer(
        string $head,
        string $url = 'https://x.test/page',
        string $body = '',
        ?string $linkHeader = null,
    ): AnalyzerResult {
        $html = "<html><head>{$head}</head><body>{$body}</body></html>";

        return (new HreflangAnalyzer)->analyze(
            new Crawler($html),
            new PageContext($url, 200, 'x.test', false, [], 'https', $linkHeader),
        );
    }

    /** @return string[] */
    private function issueValues(AnalyzerResult $result): array
    {
        return array_map(fn ($i) => $i->value, $result->issues);
    }

    public function test_valid_multilingual_set_has_no_issues_and_populates_data(): void
    {
        $head = '<link rel="alternate" hreflang="en" href="https://x.test/page">'.
            '<link rel="alternate" hreflang="de" href="https://x.test/de/page">'.
            '<link rel="alternate" hreflang="x-default" href="https://x.test/page">';

        $result = $this->runAnalyzer($head);

        $this->assertSame([], $this->issueValues($result));
        $this->assertNotNull($result->data['hreflang']);
        $this->assertCount(3, $result->data['hreflang']);
        $this->assertSame('en', $result->data['hreflang'][0]['lang']);
        $this->assertSame('https://x.test/page', $result->data['hreflang'][0]['href']);
    }

    public function test_invalid_code_flagged(): void
    {
        $head = '<link rel="alternate" hreflang="en" href="https://x.test/page">'.
            '<link rel="alternate" hreflang="zz-ZZ" href="https://x.test/zz/page">'.
            '<link rel="alternate" hreflang="x-default" href="https://x.test/page">';

        $codes = $this->issueValues($this->runAnalyzer($head));

        $this->assertContains('hreflang_incorrect_codes', $codes);
    }

    public function test_duplicate_language_flagged(): void
    {
        $head = '<link rel="alternate" hreflang="en" href="https://x.test/page">'.
            '<link rel="alternate" hreflang="en" href="https://x.test/en2/page">'.
            '<link rel="alternate" hreflang="x-default" href="https://x.test/page">';

        $codes = $this->issueValues($this->runAnalyzer($head));

        $this->assertContains('hreflang_multiple_entries', $codes);
    }

    public function test_outside_head_flagged(): void
    {
        $body = '<link rel="alternate" hreflang="de" href="https://x.test/de/page">';

        $codes = $this->issueValues($this->runAnalyzer('', 'https://x.test/page', $body));

        $this->assertContains('hreflang_outside_head', $codes);
    }

    public function test_missing_self_reference_flagged(): void
    {
        $head = '<link rel="alternate" hreflang="de" href="https://x.test/de/page">'.
            '<link rel="alternate" hreflang="x-default" href="https://x.test/de/page">';

        $codes = $this->issueValues($this->runAnalyzer($head));

        $this->assertContains('hreflang_missing_self_reference', $codes);
    }

    public function test_missing_x_default_flagged(): void
    {
        $head = '<link rel="alternate" hreflang="en" href="https://x.test/page">'.
            '<link rel="alternate" hreflang="de" href="https://x.test/de/page">';

        $codes = $this->issueValues($this->runAnalyzer($head));

        $this->assertContains('hreflang_missing_x_default', $codes);
    }

    public function test_not_using_canonical_flagged(): void
    {
        $head = '<link rel="canonical" href="https://x.test/other-page">'.
            '<link rel="alternate" hreflang="en" href="https://x.test/page">'.
            '<link rel="alternate" hreflang="x-default" href="https://x.test/page">';

        $codes = $this->issueValues($this->runAnalyzer($head));

        $this->assertContains('hreflang_not_using_canonical', $codes);
    }

    public function test_link_header_only_annotations_populate_data(): void
    {
        $linkHeader = '<https://x.test/page>; rel="alternate"; hreflang="en", '.
            '<https://x.test/de/page>; rel="alternate"; hreflang="de"';

        $result = $this->runAnalyzer('', 'https://x.test/page', '', $linkHeader);

        $this->assertNotNull($result->data['hreflang']);
        $this->assertCount(2, $result->data['hreflang']);
        $this->assertSame('en', $result->data['hreflang'][0]['lang']);
        $this->assertSame('https://x.test/page', $result->data['hreflang'][0]['href']);
        $this->assertSame('de', $result->data['hreflang'][1]['lang']);
        $this->assertSame('https://x.test/de/page', $result->data['hreflang'][1]['href']);
    }
}
