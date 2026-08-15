<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_resource_references(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
        $page = $observer->recordResponse(
            'https://example.com/',
            200,
            ['Content-Type' => ['text/html']],
            '<html><head><link rel="stylesheet" href="/app.css"><title>t</title></head><body><script src="/app.js"></script><img src="/a.png">'.str_repeat('w ', 150).'</body></html>',
            5.0,
        );

        $types = $page->resources()->pluck('type', 'url');
        $this->assertSame('css', $types['https://example.com/app.css']);
        $this->assertSame('javascript', $types['https://example.com/app.js']);
        $this->assertSame('image', $types['https://example.com/a.png']);
    }
}
