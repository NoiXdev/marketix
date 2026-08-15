<?php

namespace App\Crawler;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Probes an external resource (image/JS/CSS/font the crawler does not fetch as a page)
 * for its HTTP status and byte size. Safety mirrors LinkStatusChecker: only public,
 * resolvable hosts are probed; anything else returns null/null (no SSRF, no false data).
 */
class ResourceProbe
{
    private const TIMEOUT = 8;

    private const CONNECT_TIMEOUT = 4;

    /** Bound the GET-fallback body read for resources without a Content-Length. */
    private const MAX_PROBE_BYTES = 10_485_760; // 10 MB

    /** @return array{status: ?int, size: ?int} */
    public function probe(string $url): array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || ! UrlSafety::hostIsSafe($host)) {
            return ['status' => null, 'size' => null];
        }

        try {
            $head = Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)->head($url);
            $status = $head->status();
            $size = $this->contentLength($head);

            // HEAD rejected, or no usable Content-Length → fall back to GET.
            if (in_array($status, [405, 501], true) || $size === null) {
                $get = Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
                    ->withOptions(['stream' => true])->get($url);
                $status = $get->status();
                $size = $this->contentLength($get) ?? $this->streamedSize($get);
            }

            return ['status' => $status, 'size' => $size];
        } catch (\Throwable) {
            return ['status' => null, 'size' => null];
        }
    }

    private function contentLength(Response $response): ?int
    {
        $len = $response->header('Content-Length');

        return is_string($len) && $len !== '' && ctype_digit($len) ? (int) $len : null;
    }

    /** Sum the body in bounded chunks; null if it exceeds MAX_PROBE_BYTES (too large to measure). */
    private function streamedSize(Response $response): ?int
    {
        $body = $response->toPsrResponse()->getBody();
        $size = 0;
        while (! $body->eof()) {
            $size += strlen($body->read(8192));
            if ($size > self::MAX_PROBE_BYTES) {
                return null;
            }
        }

        return $size;
    }
}
