<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\LinkExtractor;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class LinkExtractorTest extends TestCase
{
    public function test_classifies_and_resolves_links(): void
    {
        $html = '<html><body>'
            .'<a href="/about" rel="nofollow">About</a>'
            .'<a href="https://other.test/x">Ext</a>'
            .'<a href="mailto:a@b.test">Mail</a>'
            .'<a href="#top">Top</a>'
            .'</body></html>';

        $r = (new LinkExtractor)->analyze(new Crawler($html), new PageContext('https://x.test/page', 200, 'x.test'));
        $links = $r->data['links'];

        $this->assertCount(2, $links); // mailto + fragment skipped
        $this->assertSame('https://x.test/about', $links[0]['to_url']);
        $this->assertSame('internal', $links[0]['type']);
        $this->assertSame('nofollow', $links[0]['rel']);
        $this->assertSame('About', $links[0]['anchor']);
        $this->assertSame('external', $links[1]['type']);
    }
}
