<?php

namespace App\Jobs;

use App\Crawler\PageScreenshotter;
use App\Crawler\ResourceClassifier;
use App\Models\Crawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Captures desktop + mobile screenshots for every crawled HTML page, when the crawl
 * opted in (capture_screenshots). Runs after AggregateCrawlJob (chained) so it only
 * updates the screenshot columns and never races the aggregation's page writes.
 */
class CaptureCrawlScreenshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** No timeout: capturing many pages with headless Chrome can take a long time. */
    public int $timeout = 0;

    public int $tries = 1;

    public function __construct(public Crawl $crawl) {}

    public function handle(PageScreenshotter $screenshotter): void
    {
        $disk = config('filesystems.default');

        $this->crawl->pages()
            ->where('content_category', ResourceClassifier::HTML)
            ->whereNotNull('status_code')
            ->chunkById(50, function (Collection $pages) use ($screenshotter, $disk) {
                foreach ($pages as $page) {
                    $paths = $screenshotter->capture(
                        $page->final_url ?: $page->url,
                        $disk,
                        "crawl-screenshots/{$this->crawl->id}/{$page->id}",
                    );

                    $page->update([
                        'screenshot_desktop_path' => $paths['desktop'],
                        'screenshot_mobile_path' => $paths['mobile'],
                    ]);
                }
            });
    }
}
