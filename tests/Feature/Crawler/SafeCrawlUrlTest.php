<?php

namespace Tests\Feature\Crawler;

use App\Rules\SafeCrawlUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeCrawlUrlTest extends TestCase
{
    private function passes(string $url): bool
    {
        return Validator::make(['u' => $url], ['u' => [new SafeCrawlUrl]])->passes();
    }

    public function test_rejects_non_http_schemes_and_localhost(): void
    {
        $this->assertFalse($this->passes('ftp://example.com'));
        $this->assertFalse($this->passes('http://localhost'));
        $this->assertFalse($this->passes('http://127.0.0.1'));
        $this->assertFalse($this->passes('http://192.168.0.1'));
        $this->assertFalse($this->passes('not-a-url'));
    }

    public function test_allows_public_https_url(): void
    {
        $this->assertTrue($this->passes('https://example.com/path'));
    }
}
