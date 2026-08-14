<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageScreenshotter;
use App\Jobs\CaptureCrawlScreenshotsJob;
use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureCrawlScreenshotsJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeScreenshotter(): void
    {
        $this->app->instance(PageScreenshotter::class, new class extends PageScreenshotter
        {
            public function capture(string $url, string $disk, string $pathPrefix): array
            {
                return ['desktop' => "{$pathPrefix}-desktop.jpg", 'mobile' => "{$pathPrefix}-mobile.jpg"];
            }
        });
    }

    public function test_captures_screenshots_for_html_pages_only(): void
    {
        $this->fakeScreenshotter();

        $crawl = Crawl::factory()->create(['capture_screenshots' => true]);
        $html = CrawlPage::factory()->for($crawl)->create(['content_category' => 'html', 'status_code' => 200]);
        $asset = CrawlPage::factory()->for($crawl)->create(['content_category' => 'image', 'status_code' => 200]);

        (new CaptureCrawlScreenshotsJob($crawl))->handle(app(PageScreenshotter::class));

        $html->refresh();
        $asset->refresh();

        $this->assertNotNull($html->screenshot_desktop_path);
        $this->assertNotNull($html->screenshot_mobile_path);
        $this->assertStringContainsString($html->id, $html->screenshot_desktop_path);

        // Non-HTML resources are not screenshotted.
        $this->assertNull($asset->screenshot_desktop_path);
        $this->assertNull($asset->screenshot_mobile_path);
    }
}
