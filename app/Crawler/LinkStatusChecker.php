<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Http;

/**
 * Probes the HTTP status of a link target (typically an external link the crawler
 * itself does not follow) so broken links (4xx/5xx) can be surfaced.
 *
 * Safety: only public, resolvable hosts are probed. Private/reserved/unresolvable
 * hosts return null (skipped) — this both prevents SSRF and avoids false positives
 * from transient DNS/connection failures. Redirects are followed, so the final
 * status is what's reported.
 */
class LinkStatusChecker
{
    private const TIMEOUT = 8;

    private const CONNECT_TIMEOUT = 4;

    public function status(string $url): ?int
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || ! UrlSafety::hostIsSafe($host)) {
            return null;
        }

        try {
            $status = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->head($url)
                ->status();

            // Some servers reject HEAD — retry with GET before trusting the result.
            if (in_array($status, [405, 501], true)) {
                $status = Http::timeout(self::TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->get($url)
                    ->status();
            }

            return $status;
        } catch (\Throwable) {
            return null;
        }
    }
}
