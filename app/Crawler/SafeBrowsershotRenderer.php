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
