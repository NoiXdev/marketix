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
    /**
     * Memoized detector. Building CrawlerDetect compiles its regex pattern
     * lists, so under a long-lived worker (Octane) we reuse one instance
     * across clicks instead of rebuilding it per job. isCrawler() takes the
     * UA per call, so a single shared instance is safe.
     */
    private static ?CrawlerDetect $detector = null;

    public static function isBot(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        return (self::$detector ??= new CrawlerDetect)->isCrawler($ua);
    }
}
