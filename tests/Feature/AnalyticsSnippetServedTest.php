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
