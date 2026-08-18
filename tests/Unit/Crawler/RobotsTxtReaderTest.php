<?php

namespace Tests\Unit\Crawler;

use App\Crawler\RobotsTxtReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RobotsTxtReaderTest extends TestCase
{
    public function test_specific_group_disallowed_from_root(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: GPTBot\nDisallow: /", 200),
        ]);

        $blocked = (new RobotsTxtReader)->blockedAiBots('https://example.com/');

        $this->assertSame(['GPTBot'], $blocked);
    }

    public function test_allow_all_returns_no_blocked_bots(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
        ]);

        $blocked = (new RobotsTxtReader)->blockedAiBots('https://example.com/');

        $this->assertSame([], $blocked);
    }

    public function test_wildcard_disallow_root_blocks_all_ai_bots(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200),
        ]);

        $blocked = (new RobotsTxtReader)->blockedAiBots('https://example.com/');

        $this->assertEqualsCanonicalizing([
            'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai', 'Claude-Web',
            'PerplexityBot', 'Google-Extended', 'CCBot', 'Bytespider', 'Applebot-Extended',
            'Amazonbot', 'Meta-ExternalAgent',
        ], $blocked);
    }

    public function test_missing_robots_returns_empty(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
        ]);

        $this->assertSame([], (new RobotsTxtReader)->blockedAiBots('https://example.com/'));
    }

    public function test_most_specific_group_wins_over_wildcard(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nDisallow:", 200
            ),
        ]);

        $blocked = (new RobotsTxtReader)->blockedAiBots('https://example.com/');

        $this->assertNotContains('GPTBot', $blocked);
        $this->assertContains('ClaudeBot', $blocked);
    }

    public function test_invalid_url_returns_empty(): void
    {
        $this->assertSame([], (new RobotsTxtReader)->blockedAiBots('not-a-url'));
    }
}
