<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches and caches website favicons without involving any third party. The
 * favicon is retrieved directly from the referrer's own origin (either
 * /favicon.ico or the first <link rel="icon"> declared in its homepage) so no
 * outside service ever learns which domains are stored.
 *
 * Results — hits and misses alike — are cached on the configured filesystem
 * disk (FILESYSTEM_DISK, overridable via FAVICON_DISK) to keep the analytics
 * pages from re-probing the same hosts on every render. All outbound requests
 * are SSRF-guarded: the host must resolve to a public IP and redirects are not
 * followed automatically.
 */
class FaviconFetcher
{
    private const DIR = 'favicons';

    private const MAX_BYTES = 102_400;        // 100 KB cap on a favicon

    private const HOMEPAGE_SCAN_BYTES = 262_144; // 256 KB of HTML scanned for <link>

    private const HIT_TTL = 2_592_000;        // 30 days

    private const MISS_TTL = 172_800;         // 2 days

    private const TIMEOUT = 4;

    public function __construct(
        private HttpFactory $http,
        private DnsResolver $dns,
    ) {}

    /**
     * The filesystem disk favicons are cached on: FAVICON_DISK when configured,
     * otherwise the application's default disk (FILESYSTEM_DISK).
     */
    private function disk(): Filesystem
    {
        return Storage::disk(config('services.favicon.disk'));
    }

    /**
     * Return the cached favicon for a host as [bytes, contentType], or null if
     * the host has (recently) no retrievable icon. Fetches and caches on a miss.
     *
     * @return array{0: string, 1: string}|null
     */
    public function get(string $host): ?array
    {
        $host = $this->normalizeHost($host);
        if ($host === null) {
            return null;
        }

        $disk = $this->disk();
        $base = self::DIR.'/'.sha1($host);
        $binPath = $base.'.bin';
        $ctPath = $base.'.ct';
        $missPath = $base.'.miss';

        if ($disk->exists($binPath) && $this->isFresh($disk->lastModified($binPath), self::HIT_TTL)) {
            $ct = $disk->exists($ctPath) ? $disk->get($ctPath) : 'image/x-icon';

            return [$disk->get($binPath), $ct];
        }

        if ($disk->exists($missPath) && $this->isFresh($disk->lastModified($missPath), self::MISS_TTL)) {
            return null;
        }

        $icon = $this->fetch($host);

        if ($icon === null) {
            $disk->put($missPath, (string) time());

            return null;
        }

        [$bytes, $ct] = $icon;
        $disk->put($binPath, $bytes);
        $disk->put($ctPath, $ct);
        $disk->delete($missPath);

        return [$bytes, $ct];
    }

    /** @return array{0: string, 1: string}|null */
    private function fetch(string $host): ?array
    {
        if (! $this->hostIsPublic($host)) {
            return null;
        }

        // 1) The conventional /favicon.ico at the origin root.
        $direct = $this->fetchImage("https://{$host}/favicon.ico");
        if ($direct !== null) {
            return $direct;
        }

        // 2) First declared <link rel="icon"> / apple-touch-icon in the homepage.
        $iconUrl = $this->discoverFromHomepage($host);
        if ($iconUrl !== null) {
            return $this->fetchImage($iconUrl);
        }

        return null;
    }

    /** Parse the homepage HTML for an icon URL, resolved against the origin. */
    private function discoverFromHomepage(string $host): ?string
    {
        try {
            $response = $this->http
                ->timeout(self::TIMEOUT)
                ->withOptions(['allow_redirects' => false])
                ->get("https://{$host}/");
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $html = substr($response->body(), 0, self::HOMEPAGE_SCAN_BYTES);

        // Match <link ... rel="... icon ..." ... href="..."> in either attribute order.
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            return null;
        }

        foreach ($tags[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*["\']([^"\']*)["\']/i', $tag, $rel)) {
                continue;
            }
            if (! preg_match('/\bicon\b/i', $rel[1])) {
                continue;
            }
            if (! preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $href)) {
                continue;
            }

            $resolved = $this->resolveUrl($host, trim($href[1]));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /** Resolve an icon href against the https origin; only same-host https URLs are allowed. */
    private function resolveUrl(string $host, string $href): ?string
    {
        if ($href === '' || str_starts_with($href, 'data:')) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        if (preg_match('#^https?://#i', $href)) {
            $parts = parse_url($href);
            if (($parts['host'] ?? null) !== $host) {
                return null; // refuse cross-host icon references
            }

            return 'https://'.$host.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        // Relative path.
        $path = str_starts_with($href, '/') ? $href : '/'.$href;

        return 'https://'.$host.$path;
    }

    /** @return array{0: string, 1: string}|null */
    private function fetchImage(string $url): ?array
    {
        try {
            $response = $this->http
                ->timeout(self::TIMEOUT)
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $ct = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! $this->looksLikeImage($ct, $bytes)) {
            return null;
        }

        return [$bytes, $ct !== '' ? $ct : 'image/x-icon'];
    }

    private function looksLikeImage(string $contentType, string $bytes): bool
    {
        if (str_starts_with($contentType, 'image/')) {
            return true;
        }

        // Some servers send favicon.ico as octet-stream; sniff common magic bytes.
        $magic = substr($bytes, 0, 4);

        return $magic === "\x00\x00\x01\x00"           // ICO
            || str_starts_with($bytes, "\x89PNG")       // PNG
            || str_starts_with($bytes, 'GIF8')          // GIF
            || str_starts_with($bytes, "\xFF\xD8\xFF")  // JPEG
            || str_starts_with(ltrim($bytes), '<svg')   // SVG
            || str_contains(substr($bytes, 0, 256), '<svg');
    }

    private function hostIsPublic(string $host): bool
    {
        $ips = $this->dns->resolveIps($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false; // any private/reserved address disqualifies the host
            }
        }

        return true;
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        // Strip a leading scheme/path if one slipped in.
        $host = preg_replace('#^https?://#', '', $host);
        $host = explode('/', $host)[0];

        if ($host === '' || strlen($host) > 253 || ! str_contains($host, '.')) {
            return null;
        }

        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        return $host;
    }

    private function isFresh(int $modifiedAt, int $ttl): bool
    {
        return (time() - $modifiedAt) < $ttl;
    }
}
