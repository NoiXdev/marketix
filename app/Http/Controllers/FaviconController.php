<?php

namespace App\Http\Controllers;

use App\Services\FaviconFetcher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FaviconController extends Controller
{
    /**
     * Neutral globe used whenever a domain has no retrievable favicon, so the
     * analytics lists always render a consistent prefix.
     */
    private const FALLBACK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#94a3b8"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';

    public function __construct(private FaviconFetcher $fetcher) {}

    public function show(Request $request, string $domain): Response
    {
        $icon = $this->fetcher->get($domain);

        if ($icon === null) {
            return response(self::FALLBACK_SVG, 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        [$bytes, $contentType] = $icon;

        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=604800',
            'ETag' => '"'.sha1($bytes).'"',
        ]);
    }
}
