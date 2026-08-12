<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\ImageAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class ImageAnalyzerTest extends TestCase
{
    public function test_flags_images_without_alt(): void
    {
        $html = '<html><body><img src="/a.png" alt="ok"><img src="/b.png"><img src="/c.png" alt=""></body></html>';
        $r = (new ImageAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertSame(['/b.png', '/c.png'], $r->data['images_missing_alt']);
        $this->assertContains(IssueCode::MissingAltText, $r->issues);
    }

    public function test_clean_page_has_no_issue(): void
    {
        $r = (new ImageAnalyzer)->analyze(new Crawler('<html><body><img src="/a.png" alt="ok"></body></html>'), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertSame([], $r->data['images_missing_alt']);
        $this->assertSame([], $r->issues);
    }
}
