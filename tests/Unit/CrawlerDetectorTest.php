<?php

namespace Tests\Unit;

use App\Support\CrawlerDetector;
use PHPUnit\Framework\TestCase;

class CrawlerDetectorTest extends TestCase
{
    public function test_detects_known_crawlers(): void
    {
        $this->assertTrue(CrawlerDetector::isBot(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ));
        $this->assertTrue(CrawlerDetector::isBot(
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'
        ));
    }

    public function test_real_browser_is_not_a_bot(): void
    {
        $this->assertFalse(CrawlerDetector::isBot(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ));
    }

    public function test_empty_user_agent_is_not_a_bot(): void
    {
        $this->assertFalse(CrawlerDetector::isBot(''));
    }
}
