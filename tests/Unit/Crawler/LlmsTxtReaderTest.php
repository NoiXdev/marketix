<?php

namespace Tests\Unit\Crawler;

use App\Crawler\LlmsTxtReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmsTxtReaderTest extends TestCase
{
    public function test_returns_true_for_non_empty_llms_txt(): void
    {
        Http::fake([
            'example.com/llms.txt' => Http::response("# Example\n\nSome content.", 200),
        ]);

        $this->assertTrue((new LlmsTxtReader)->exists('https://example.com/'));
    }

    public function test_returns_false_for_404(): void
    {
        Http::fake([
            'example.com/llms.txt' => Http::response('', 404),
        ]);

        $this->assertFalse((new LlmsTxtReader)->exists('https://example.com/'));
    }

    public function test_returns_false_for_empty_body(): void
    {
        Http::fake([
            'example.com/llms.txt' => Http::response('', 200),
        ]);

        $this->assertFalse((new LlmsTxtReader)->exists('https://example.com/'));
    }

    public function test_invalid_url_returns_false(): void
    {
        $this->assertFalse((new LlmsTxtReader)->exists('not-a-url'));
    }
}
