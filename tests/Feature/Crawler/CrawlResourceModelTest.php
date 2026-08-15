<?php

namespace Tests\Feature\Crawler;

use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\CrawlResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlResourceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_relations_and_cast(): void
    {
        $crawl = Crawl::factory()->create();
        $page = CrawlPage::factory()->for($crawl)->create();
        $res = CrawlResource::factory()->create([
            'crawl_id' => $crawl->id, 'from_page_id' => $page->id,
            'url' => 'https://example.com/app.css', 'type' => 'css', 'is_internal' => true,
        ]);

        $this->assertTrue($crawl->resources()->whereKey($res->id)->exists());
        $this->assertTrue($page->resources()->whereKey($res->id)->exists());
        $this->assertIsBool($res->refresh()->is_internal);
    }
}
