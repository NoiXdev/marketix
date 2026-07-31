<?php

namespace App\Support;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Thin wrapper over jaybizzle/crawler-detect for classifying click traffic.
 * Kept as a static helper to mirror App\Support\UserAgent and stay easy to
 * call from the queued statistic job.
 */
class CrawlerDetector
{
    public static function isBot(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        return (new CrawlerDetect)->isCrawler($ua);
    }
}
