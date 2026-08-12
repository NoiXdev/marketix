<?php

namespace Tests\Feature\Crawler;

use App\Enums\CrawlStatus;
use App\Jobs\AggregateCrawlJob;
use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Crawler\Crawler;
use Tests\TestCase;

class RunCrawlJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_hook_marks_crawl_failed(): void
    {
        $crawl = Crawl::factory()->create(['status' => 'running']);

        (new RunCrawlJob($crawl))->failed(new \RuntimeException('boom'));

        $crawl->refresh();
        $this->assertSame(CrawlStatus::Failed->value, $crawl->status->value);
        $this->assertStringContainsString('boom', $crawl->error);
    }

    public function test_single_page_crawl_records_page_and_dispatches_aggregate(): void
    {
        Bus::fake([AggregateCrawlJob::class]);

        $crawl = Crawl::factory()->create([
            'start_url' => 'https://example.test/',
            'mode' => 'single_page',
            'respect_robots' => false,
        ]);

        $html = '<html><head><title>Fx</title></head><body><h1>Fx</h1>'
            .str_repeat('word ', 150).'</body></html>';

        // Subclass injects spatie's fake() into the configured crawler so the whole
        // pipeline (config -> observer -> persistence) runs without live HTTP.
        $job = new class($crawl, $html) extends RunCrawlJob
        {
            public function __construct(Crawl $crawl, private string $html)
            {
                parent::__construct($crawl);
            }

            protected function buildCrawler(CrawlPageObserver $observer): Crawler
            {
                return parent::buildCrawler($observer)->fake([$this->crawl->start_url => $this->html]);
            }
        };

        $job->handle();

        $crawl->refresh();
        $this->assertGreaterThanOrEqual(1, $crawl->pages_crawled);
        Bus::assertDispatched(AggregateCrawlJob::class);
    }
}
