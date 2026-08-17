<?php

namespace Tests\Unit\Crawler;

use App\Crawler\HreflangCodes;
use PHPUnit\Framework\TestCase;

class HreflangCodesTest extends TestCase
{
    public function test_valid_codes(): void
    {
        foreach (['en', 'de', 'en-GB', 'de-DE', 'zh-Hans', 'zh-Hant-HK', 'es-419', 'x-default', 'pt-BR'] as $c) {
            $this->assertTrue(HreflangCodes::isValid($c), $c);
        }
    }

    public function test_invalid_codes(): void
    {
        foreach (['', 'en-UK', 'zz', 'de_DE', 'EN', 'english', 'en-gb', 'x_default', '123'] as $c) {
            $this->assertFalse(HreflangCodes::isValid($c), $c);
        }
    }
}
