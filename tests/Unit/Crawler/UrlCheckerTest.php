<?php

namespace Tests\Unit\Crawler;

use App\Crawler\UrlChecker;
use PHPUnit\Framework\TestCase;

class UrlCheckerTest extends TestCase
{
    public function test_clean_url_has_no_issues(): void
    {
        $this->assertSame([], UrlChecker::issues('https://example.com/blog/post-1'));
    }

    public function test_each_hygiene_check_fires(): void
    {
        $this->assertContains('url_non_ascii', UrlChecker::issues('https://example.com/café'));
        $this->assertContains('url_underscores', UrlChecker::issues('https://example.com/foo_bar'));
        $this->assertContains('url_uppercase', UrlChecker::issues('https://example.com/Foo'));
        $this->assertContains('url_contains_space', UrlChecker::issues('https://example.com/foo bar'));
        $this->assertContains('url_contains_space', UrlChecker::issues('https://example.com/foo%20bar'));
        $this->assertContains('url_multiple_slashes', UrlChecker::issues('https://example.com/foo//bar'));
        $this->assertContains('url_repetitive_path', UrlChecker::issues('https://example.com/blog/blog/post'));
    }

    public function test_ga_tracking_params(): void
    {
        $this->assertContains('url_ga_tracking_params', UrlChecker::issues('https://example.com/p?utm_source=x'));
        $this->assertContains('url_ga_tracking_params', UrlChecker::issues('https://example.com/p?gclid=abc'));
        $this->assertNotContains('url_ga_tracking_params', UrlChecker::issues('https://example.com/p?ref=x'));
    }

    public function test_hygiene_checks_read_path_only_not_query(): void
    {
        // Uppercase + underscore only in the query → clean path → not flagged.
        $issues = UrlChecker::issues('https://example.com/blog?ref=AbC_Def');
        $this->assertNotContains('url_uppercase', $issues);
        $this->assertNotContains('url_underscores', $issues);
    }

    public function test_repetitive_path_only_flags_adjacent_segments(): void
    {
        $this->assertNotContains('url_repetitive_path', UrlChecker::issues('https://example.com/a/b/a'));
    }

    public function test_length_boundary_at_115(): void
    {
        $base = 'https://example.com/';
        $at115 = $base.str_repeat('a', 115 - mb_strlen($base));
        $over = $base.str_repeat('a', 116 - mb_strlen($base));
        $this->assertNotContains('url_over_115_chars', UrlChecker::issues($at115));
        $this->assertContains('url_over_115_chars', UrlChecker::issues($over));
    }

    public function test_deferred_codes_are_never_emitted(): void
    {
        $issues = UrlChecker::issues('https://example.com/search?q=shoes');
        $this->assertNotContains('url_parameters', $issues);
        $this->assertNotContains('url_internal_search', $issues);
        $this->assertNotContains('url_broken_bookmark', $issues);
    }

    public function test_root_url_has_no_path_issues(): void
    {
        $this->assertSame([], UrlChecker::issues('https://example.com'));
        $this->assertSame([], UrlChecker::issues('https://example.com/'));
    }
}
