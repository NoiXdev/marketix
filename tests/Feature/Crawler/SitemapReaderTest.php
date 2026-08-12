<?php

namespace Tests\Feature\Crawler;

use App\Crawler\SitemapReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitemapReaderTest extends TestCase
{
    public function test_parses_loc_entries(): void
    {
        Http::fake([
            'x.test/sitemap.xml' => Http::response(
                '<?xml version="1.0"?><urlset><url><loc>https://x.test/</loc></url>'
                .'<url><loc>https://x.test/about</loc></url></urlset>', 200),
        ]);

        $urls = (new SitemapReader)->urlsFor('https://x.test/');

        $this->assertSame(['https://x.test/', 'https://x.test/about'], $urls);
    }

    public function test_missing_sitemap_returns_empty(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertSame([], (new SitemapReader)->urlsFor('https://x.test/'));
    }
}
