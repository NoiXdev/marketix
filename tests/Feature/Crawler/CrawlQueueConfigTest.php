<?php

namespace Tests\Feature\Crawler;

use Tests\TestCase;

class CrawlQueueConfigTest extends TestCase
{
    public function test_database_queue_retry_after_survives_long_crawls(): void
    {
        // The crawl jobs have no timeout and can run for a long time. On the database
        // queue with several workers, retry_after must comfortably exceed that runtime,
        // otherwise a second worker re-reserves a still-running crawl and fails it early.
        $retryAfter = (int) config('queue.connections.database.retry_after');

        $this->assertGreaterThanOrEqual(3600, $retryAfter, 'database queue retry_after must give long crawls ample headroom');
    }
}
