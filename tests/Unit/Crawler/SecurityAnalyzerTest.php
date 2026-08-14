<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\SecurityAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class SecurityAnalyzerTest extends TestCase
{
    /** @param array<string,string> $headers @return string[] */
    private function runAnalyzer(string $html, array $headers = [], string $scheme = 'https', string $url = 'https://example.com/'): array
    {
        $ctx = new PageContext($url, 200, 'example.com', false, $headers, $scheme);
        $result = (new SecurityAnalyzer)->analyze(new Crawler($html), $ctx);

        return array_map(fn ($i) => $i->value, $result->issues);
    }

    public function test_secure_page_with_all_headers_emits_only_https_urls(): void
    {
        $headers = [
            'strict-transport-security' => 'max-age=1',
            'content-security-policy' => "default-src 'self'",
            'x-frame-options' => 'DENY',
            'x-content-type-options' => 'nosniff',
            'referrer-policy' => 'no-referrer',
        ];
        $codes = $this->runAnalyzer('<html><body>ok</body></html>', $headers);

        $this->assertSame(['https_urls'], $codes);
    }

    public function test_missing_headers_are_flagged(): void
    {
        $codes = $this->runAnalyzer('<html><body>ok</body></html>');

        $this->assertContains('missing_hsts_header', $codes);
        $this->assertContains('missing_csp_header', $codes);
        $this->assertContains('missing_x_content_type_options', $codes);
        $this->assertContains('missing_x_frame_options', $codes);
        $this->assertContains('missing_referrer_policy', $codes);
    }

    public function test_csp_frame_ancestors_satisfies_x_frame_options(): void
    {
        $codes = $this->runAnalyzer('<html><body>ok</body></html>', ['content-security-policy' => "frame-ancestors 'none'"]);

        $this->assertNotContains('missing_x_frame_options', $codes);
    }

    public function test_meta_referrer_satisfies_referrer_policy(): void
    {
        $codes = $this->runAnalyzer('<html><head><meta name="referrer" content="no-referrer"></head><body>ok</body></html>');

        $this->assertNotContains('missing_referrer_policy', $codes);
    }

    public function test_http_page_emits_http_urls_not_https(): void
    {
        $codes = $this->runAnalyzer('<html><body>ok</body></html>', [], 'http', 'http://example.com/');

        $this->assertContains('http_urls', $codes);
        $this->assertNotContains('https_urls', $codes);
        $this->assertNotContains('missing_hsts_header', $codes); // HSTS only meaningful on https
    }

    public function test_mixed_content_and_protocol_relative_resources(): void
    {
        $html = '<html><body><img src="http://cdn.test/a.png"><script src="//cdn.test/b.js"></script></body></html>';
        $codes = $this->runAnalyzer($html);

        $this->assertContains('mixed_content', $codes);
        $this->assertContains('protocol_relative_resource_links', $codes);
    }

    public function test_unsafe_cross_origin_link(): void
    {
        $unsafe = $this->runAnalyzer('<html><body><a href="https://other.test/x" target="_blank">x</a></body></html>');
        $this->assertContains('unsafe_cross_origin_links', $unsafe);

        $safe = $this->runAnalyzer('<html><body><a href="https://other.test/x" target="_blank" rel="noopener">x</a></body></html>');
        $this->assertNotContains('unsafe_cross_origin_links', $safe);

        $sameHost = $this->runAnalyzer('<html><body><a href="https://example.com/x" target="_blank">x</a></body></html>');
        $this->assertNotContains('unsafe_cross_origin_links', $sameHost);
    }

    public function test_insecure_forms(): void
    {
        $onHttp = $this->runAnalyzer('<html><body><form action="/submit"></form></body></html>', [], 'http', 'http://example.com/');
        $this->assertContains('form_on_http', $onHttp);

        $insecureAction = $this->runAnalyzer('<html><body><form action="http://example.com/submit"></form></body></html>');
        $this->assertContains('form_url_insecure', $insecureAction);
    }
}
