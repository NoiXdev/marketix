<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\GeoAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class GeoAnalyzerTest extends TestCase
{
    private function runAnalyzer(
        string $body,
        string $head = '',
        string $url = 'https://x.test/page',
    ): AnalyzerResult {
        $html = "<html><head>{$head}</head><body>{$body}</body></html>";

        return (new GeoAnalyzer)->analyze(
            new Crawler($html),
            new PageContext($url, 200, 'x.test'),
        );
    }

    /** @return string[] */
    private function issueValues(AnalyzerResult $result): array
    {
        return array_map(fn ($i) => $i->value, $result->issues);
    }

    public function test_main_present_has_no_semantic_html_issue(): void
    {
        $codes = $this->issueValues($this->runAnalyzer('<main><p>Content here.</p></main>'));

        $this->assertNotContains('no_semantic_html', $codes);
    }

    public function test_neither_main_nor_article_flags_no_semantic_html(): void
    {
        $codes = $this->issueValues($this->runAnalyzer('<div><p>Content here.</p></div>'));

        $this->assertContains('no_semantic_html', $codes);
    }

    public function test_time_datetime_satisfies_date_signal(): void
    {
        $body = '<main><time datetime="2026-01-01">Jan 1</time></main>';

        $codes = $this->issueValues($this->runAnalyzer($body));

        $this->assertNotContains('missing_date_signal', $codes);
    }

    public function test_json_ld_date_modified_satisfies_date_signal(): void
    {
        $body = '<main><p>Content</p></main>'.
            '<script type="application/ld+json">{"@type":"Article","dateModified":"2026-01-01"}</script>';

        $codes = $this->issueValues($this->runAnalyzer($body));

        $this->assertNotContains('missing_date_signal', $codes);
    }

    public function test_article_published_time_meta_satisfies_date_signal(): void
    {
        $head = '<meta property="article:published_time" content="2026-01-01">';

        $codes = $this->issueValues($this->runAnalyzer('<main><p>Content</p></main>', $head));

        $this->assertNotContains('missing_date_signal', $codes);
    }

    public function test_no_date_signal_flags_missing_date_signal(): void
    {
        $codes = $this->issueValues($this->runAnalyzer('<main><p>Content here.</p></main>'));

        $this->assertContains('missing_date_signal', $codes);
    }

    public function test_thin_body_with_spa_root_flags_js_dependent_content(): void
    {
        $codes = $this->issueValues($this->runAnalyzer('<div id="root"></div>'));

        $this->assertContains('js_dependent_content', $codes);
    }

    public function test_content_rich_body_without_spa_marker_has_no_issues(): void
    {
        $longParagraph = '<p>'.str_repeat('This is a long, content-rich paragraph. ', 20).'</p>';
        $head = '<meta property="article:published_time" content="2026-01-01">';

        $codes = $this->issueValues($this->runAnalyzer('<main>'.$longParagraph.'</main>', $head));

        $this->assertNotContains('no_semantic_html', $codes);
        $this->assertNotContains('missing_date_signal', $codes);
        $this->assertNotContains('js_dependent_content', $codes);
    }
}
