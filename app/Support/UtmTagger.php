<?php

namespace App\Support;

class UtmTagger
{
    /** UTM parameters in the order they are appended, without the "utm_" prefix. */
    public const KEYS = ['source', 'medium', 'campaign', 'term', 'content'];

    /**
     * Trimmed, non-empty UTM values keyed by KEYS, or null when none are set.
     *
     * @param  array<string, mixed>|null  $utm
     * @return array<string, string>|null
     */
    public static function normalize(?array $utm): ?array
    {
        $clean = [];
        foreach (self::KEYS as $key) {
            $value = trim((string) ($utm[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Append the link's UTM parameters to an http(s) target. Parameters the
     * target already carries win, so hand-tagged URLs are never rewritten.
     * The existing query string and fragment are kept byte for byte.
     *
     * @param  array<string, string>|null  $utm
     */
    public static function apply(string $target, ?array $utm): string
    {
        $utm = self::normalize($utm);
        $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
        if ($utm === null || ! in_array($scheme, ['http', 'https'], true)) {
            return $target;
        }

        [$base, $fragment] = array_pad(explode('#', $target, 2), 2, null);
        parse_str((string) parse_url($base, PHP_URL_QUERY), $existing);

        $add = [];
        foreach ($utm as $key => $value) {
            if (! array_key_exists('utm_'.$key, $existing)) {
                $add['utm_'.$key] = $value;
            }
        }
        if ($add === []) {
            return $target;
        }

        $separator = ! str_contains($base, '?') ? '?' : (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&');

        return $base.$separator.http_build_query($add, '', '&', PHP_QUERY_RFC3986).($fragment === null ? '' : '#'.$fragment);
    }
}
