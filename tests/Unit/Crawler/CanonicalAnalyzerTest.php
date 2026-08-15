<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\CanonicalAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class CanonicalAnalyzerTest extends TestCase
{
    /** @return string[] */
    private function runAnalyzer(string $head, string $url = 'https://x.test/page', string $body = ''): array
    {
        $html = "<html><head>{$head}</head><body>{$body}</body></html>";
        $result = (new CanonicalAnalyzer)->analyze(new Crawler($html), new PageContext($url, 200, 'x.test'));

        return array_map(fn ($i) => $i->value, $result->issues);
    }

    public function test_missing_canonical(): void
    {
        $this->assertSame(['missing_canonical'], $this->runAnalyzer(''));
    }

    public function test_self_referencing_and_has(): void
    {
        $codes = $this->runAnalyzer('<link rel="canonical" href="https://x.test/page">');
        $this->assertContains('has_canonical', $codes);
        $this->assertContains('canonical_self_referencing', $codes);
    }

    public function test_multiple_and_conflicting(): void
    {
        $codes = $this->runAnalyzer('<link rel="canonical" href="https://x.test/a"><link rel="canonical" href="https://x.test/b">');
        $this->assertContains('multiple_canonical', $codes);
        $this->assertContains('multiple_conflicting_canonical', $codes);
    }

    public function test_relative_fragment_invalid(): void
    {
        $this->assertContains('canonical_is_relative', $this->runAnalyzer('<link rel="canonical" href="/page">'));
        $this->assertContains('canonical_fragment_url', $this->runAnalyzer('<link rel="canonical" href="https://x.test/page#a">'));
        $this->assertContains('canonical_invalid_attribute', $this->runAnalyzer('<link rel="canonical" href="">'));
    }

    public function test_outside_head(): void
    {
        $codes = $this->runAnalyzer('', 'https://x.test/page', '<link rel="canonical" href="https://x.test/page">');
        $this->assertContains('canonical_outside_head', $codes);
    }
}
