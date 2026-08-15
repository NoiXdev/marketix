<?php

namespace App\Crawler;

class UrlChecker
{
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'mc_cid', 'mc_eid',
    ];

    /**
     * Pure URL-hygiene checks on a single crawled URL.
     *
     * @return string[] IssueCode values (may be empty)
     */
    public static function issues(string $url): array
    {
        [$path, $query] = self::split($url);
        $issues = [];

        if (preg_match('/[^\x00-\x7F]/', $path)) {
            $issues[] = IssueCode::UrlNonAscii->value;
        }
        if (str_contains($path, '_')) {
            $issues[] = IssueCode::UrlUnderscores->value;
        }
        if (preg_match('/[A-Z]/', $path)) {
            $issues[] = IssueCode::UrlUppercase->value;
        }
        if (str_contains($path, ' ') || stripos($path, '%20') !== false) {
            $issues[] = IssueCode::UrlContainsSpace->value;
        }
        if (str_contains($path, '//')) {
            $issues[] = IssueCode::UrlMultipleSlashes->value;
        }
        if (self::hasRepetitiveSegments($path)) {
            $issues[] = IssueCode::UrlRepetitivePath->value;
        }
        if (self::hasTrackingParams($query)) {
            $issues[] = IssueCode::UrlGaTrackingParams->value;
        }
        if (mb_strlen($url) > 115) {
            $issues[] = IssueCode::UrlOver115Chars->value;
        }

        return $issues;
    }

    /**
     * Split into [path, query] robustly — parse_url() returns false on URLs with a
     * literal space or raw non-ASCII, which are exactly the URLs we flag. Strip
     * `scheme://host` with a regex, then separate the query.
     *
     * @return array{0: string, 1: string}
     */
    private static function split(string $url): array
    {
        $afterHost = preg_replace('#^[a-z][a-z0-9+.\-]*://[^/?\#]*#i', '', $url) ?? $url;
        $path = preg_split('/[?#]/', $afterHost, 2)[0];
        $query = preg_match('/\?([^#]*)/', $afterHost, $m) ? $m[1] : '';

        return [$path, $query];
    }

    private static function hasRepetitiveSegments(string $path): bool
    {
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        for ($i = 1, $n = count($segments); $i < $n; $i++) {
            if ($segments[$i] === $segments[$i - 1]) {
                return true;
            }
        }

        return false;
    }

    private static function hasTrackingParams(string $query): bool
    {
        if ($query === '') {
            return false;
        }
        parse_str($query, $params);

        return array_intersect_key($params, array_flip(self::TRACKING_PARAMS)) !== [];
    }
}
