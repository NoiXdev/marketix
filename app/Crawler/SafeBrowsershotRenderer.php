<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Spatie\Crawler\JavaScriptRenderers\JavaScriptRenderer;

/**
 * JS renderer for crawls with render_js enabled. Wraps Browsershot so a single
 * un-renderable URL cannot abort the whole crawl: spatie/crawler only catches
 * ProcessFailedException around rendering, so an invalid URL (e.g. one containing
 * a raw space) makes Browsershot throw FileUrlNotAllowed, which otherwise bubbles
 * up and fails the entire scan. Here any rendering failure is caught and the page
 * is recorded without rendered content instead.
 */
class SafeBrowsershotRenderer implements JavaScriptRenderer
{
    public function __construct(private Browsershot $browsershot) {}

    public function getRenderedHtml(string $url): string
    {
        // Raw spaces are always invalid in a URL and the most common reason Browsershot
        // rejects a discovered link; encode them best-effort so such URLs still render.
        $url = str_replace(' ', '%20', $url);

        // Defence in depth against SSRF: never point the headless browser at a
        // private/internal host. Re-resolving here (rather than trusting an earlier
        // check) also blocks DNS-rebinding, where a host resolved public at crawl time
        // but now points at an internal address. Note: server-side redirects are
        // already blocked by the crawler's guarded Guzzle fetch that precedes rendering;
        // blocking client-side redirects Chrome follows itself needs egress filtering.
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! UrlSafety::hostIsSafe($host)) {
            return '';
        }

        try {
            return html_entity_decode($this->browsershot->setUrl($url)->bodyHtml());
        } catch (\Throwable $e) {
            Log::warning('JS rendering failed; recording the URL without rendered content.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
