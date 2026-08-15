<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\ResourceExtractor;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class ResourceExtractorTest extends TestCase
{
    /** @return array<int, array{url:string,type:string,is_internal:bool}> */
    private function extract(string $body): array
    {
        $html = '<html><head></head><body>'.$body.'</body></html>';

        return (new ResourceExtractor)->analyze(new Crawler($html), new PageContext('https://x.test/page', 200, 'x.test'))->data['resources'];
    }

    public function test_extracts_types_and_internal_flag(): void
    {
        $res = collect($this->extract(
            '<script src="/app.js"></script>'
            .'<link rel="stylesheet" href="/app.css">'
            .'<img src="/logo.png">'
            .'<link rel="preload" as="font" href="/f.woff2">'
            .'<script src="https://cdn.test/lib.js"></script>'
        ))->keyBy('url');

        $this->assertSame('javascript', $res['https://x.test/app.js']['type']);
        $this->assertTrue($res['https://x.test/app.js']['is_internal']);
        $this->assertSame('css', $res['https://x.test/app.css']['type']);
        $this->assertSame('image', $res['https://x.test/logo.png']['type']);
        $this->assertSame('font', $res['https://x.test/f.woff2']['type']);
        $this->assertFalse($res['https://cdn.test/lib.js']['is_internal']);
    }

    public function test_dedupes_within_page_and_skips_data_uris(): void
    {
        $res = $this->extract('<img src="/a.png"><img src="/a.png"><img src="data:image/png;base64,xxxx">');
        $urls = array_column($res, 'url');
        $this->assertSame(['https://x.test/a.png'], $urls);
    }

    public function test_extracts_srcset_and_inline_background_image(): void
    {
        $res = collect($this->extract(
            '<img srcset="/a.jpg 1x, /b.jpg 2x">'
            .'<div style="background-image: url(\'/bg.png\')"></div>'
            .'<span style="background-image:url(data:image/png;base64,AAAA)"></span>'
        ))->keyBy('url');

        $this->assertSame('image', $res['https://x.test/a.jpg']['type']);
        $this->assertArrayHasKey('https://x.test/b.jpg', $res->all());
        $this->assertSame('image', $res['https://x.test/bg.png']['type']);
        // data: background is skipped by resolve().
        $this->assertFalse(collect($res)->contains(fn ($r) => str_starts_with($r['url'], 'data:')));
    }
}
