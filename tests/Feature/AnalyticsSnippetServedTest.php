<?php

namespace Tests\Feature;

use Tests\TestCase;

class AnalyticsSnippetServedTest extends TestCase
{
    public function test_snippet_source_exists_and_is_self_contained(): void
    {
        $path = resource_path('analytics/mx.js');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringContainsString('/a/event', $contents);
        $this->assertStringContainsString('/a/config/', $contents);
        $this->assertStringContainsString('data-site', $contents);
        // consent contract: strict boolean check, reacts to CMP consent changes
        $this->assertStringContainsString('marketix:consent', $contents);
        $this->assertStringContainsString('=== true', $contents);
        // no external dependencies
        $this->assertStringNotContainsString('import ', $contents);
        $this->assertStringNotContainsString('require(', $contents);

        // UTM capture from the landing URL query string.
        $this->assertStringContainsString('URLSearchParams', $contents);
        $this->assertStringContainsString('utm_', $contents);
        $this->assertStringContainsString('payload.utm', $contents);

        // Page title and hostname travel with each page view.
        $this->assertStringContainsString('document.title', $contents);
        $this->assertStringContainsString('location.hostname', $contents);

        // Custom event API
        $this->assertStringContainsString('window.marketix', $contents);
        $this->assertStringContainsString('marketix.q', $contents);
        $this->assertStringContainsString("'event'", $contents);
        $this->assertStringContainsString("'404'", $contents);

        // SPA navigation, engagement and enhanced measurement
        $this->assertStringContainsString('pushState', $contents);
        $this->assertStringContainsString('popstate', $contents);
        $this->assertStringContainsString('visibilitychange', $contents);
        $this->assertStringContainsString("type: 'engagement'", $contents);
        $this->assertStringContainsString("'outbound_click'", $contents);
        $this->assertStringContainsString("'file_download'", $contents);
        $this->assertStringContainsString("'site_search'", $contents);
    }

    public function test_snippet_is_served_with_js_content_type_and_cache_headers(): void
    {
        $response = $this->get(route('app.analytics.snippet'));

        $response->assertOk();
        $this->assertStringContainsString('javascript', strtolower((string) $response->headers->get('Content-Type')));
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->get('ETag'));
        $this->assertStringContainsString('data-site', $response->getContent());
    }

    public function test_snippet_returns_304_when_etag_matches(): void
    {
        $etag = $this->get(route('app.analytics.snippet'))->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->withHeaders(['If-None-Match' => $etag])
            ->get(route('app.analytics.snippet'))
            ->assertStatus(304);
    }
}
