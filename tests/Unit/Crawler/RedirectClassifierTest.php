<?php

namespace Tests\Unit\Crawler;

use App\Crawler\RedirectClassifier;
use PHPUnit\Framework\TestCase;

class RedirectClassifierTest extends TestCase
{
    public function test_single_redirect_is_3xx_not_chain(): void
    {
        $codes = RedirectClassifier::issues(['https://example.com/a', 'https://example.com/b'], [], '', false);
        $this->assertContains('internal_redirect_3xx', $codes);
        $this->assertNotContains('redirect_chain', $codes);
    }

    public function test_two_hops_is_chain_not_3xx(): void
    {
        $codes = RedirectClassifier::issues(['https://example.com/a', 'https://example.com/b', 'https://example.com/c'], [], '', false);
        $this->assertContains('redirect_chain', $codes);
        $this->assertNotContains('internal_redirect_3xx', $codes);
    }

    public function test_no_redirect_emits_neither(): void
    {
        $codes = RedirectClassifier::issues([], [], '', false);
        $this->assertNotContains('redirect_chain', $codes);
        $this->assertNotContains('internal_redirect_3xx', $codes);
    }

    public function test_repeated_url_in_chain_is_a_loop(): void
    {
        $codes = RedirectClassifier::issues(['https://example.com/a', 'https://example.com/b', 'https://example.com/a'], [], '', false);
        $this->assertContains('internal_redirect_loop', $codes);
    }

    public function test_http_refresh_header(): void
    {
        $codes = RedirectClassifier::issues([], ['refresh' => ['0;url=/x']], '', false);
        $this->assertContains('internal_http_refresh_redirect', $codes);
    }

    public function test_meta_refresh_in_html_only(): void
    {
        $html = '<html><head><meta http-equiv="refresh" content="0;url=/x"></head></html>';
        $this->assertContains('internal_meta_refresh_redirect', RedirectClassifier::issues([], [], $html, true));
        // Not flagged when the body is not treated as HTML …
        $this->assertNotContains('internal_meta_refresh_redirect', RedirectClassifier::issues([], [], $html, false));
        // … and not flagged when absent.
        $this->assertNotContains('internal_meta_refresh_redirect', RedirectClassifier::issues([], [], '<html><body>x</body></html>', true));
    }

    public function test_failure_issues(): void
    {
        $this->assertSame(['internal_no_response'], RedirectClassifier::failureIssues(null, false));
        $this->assertSame(['internal_redirect_loop'], RedirectClassifier::failureIssues(null, true)); // loop wins over null
        $this->assertSame(['client_error'], RedirectClassifier::failureIssues(404, false));
        $this->assertSame(['server_error'], RedirectClassifier::failureIssues(503, false));
    }
}
