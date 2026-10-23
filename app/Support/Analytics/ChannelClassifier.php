<?php

namespace App\Support\Analytics;

final class ChannelClassifier
{
    public const CHANNELS = ['direct', 'organic_search', 'paid', 'social', 'email', 'referral', 'campaign'];

    private const PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'paidsearch', 'paid_search', 'paid-search', 'paidsocial', 'paid_social', 'paid-social', 'cpm', 'cpv', 'display', 'banner', 'retargeting'];

    private const EMAIL_VALUES = ['email', 'e-mail', 'e_mail', 'mail', 'newsletter'];

    private const SOCIAL_MEDIUMS = ['social', 'social-network', 'social_network', 'social-media', 'social_media', 'sm'];

    private const SOCIAL_SOURCES = ['facebook', 'fb', 'instagram', 'ig', 'linkedin', 'twitter', 'x', 'tiktok', 'youtube', 'pinterest', 'reddit', 'xing', 'threads', 'bluesky', 'mastodon', 'whatsapp', 'telegram'];

    private const SOCIAL_HOSTS = [
        'facebook.com', 'fb.com', 'fb.me', 'instagram.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'lnkd.in',
        'reddit.com', 'youtube.com', 'youtu.be', 'pinterest.*', 'tiktok.com', 'xing.com', 'threads.net', 'bsky.app',
        'mastodon.social', 'whatsapp.com', 'wa.me', 't.me', 'snapchat.com', 'tumblr.com', 'quora.com',
        'news.ycombinator.com', 'discord.com',
    ];

    private const SEARCH_HOSTS = [
        'google.*', 'bing.com', 'duckduckgo.com', 'yahoo.*', 'ecosia.org', 'yandex.*', 'baidu.com', 'qwant.com',
        'startpage.com', 'search.brave.com', 'ask.com', 'naver.com', 'seznam.cz', 'perplexity.ai',
    ];

    /** @return array{0: string, 1: list<string>} */
    public static function expression(?string $siteDomain): array
    {
        $bindings = [];
        $medium = "LOWER(COALESCE(utm_medium, ''))";
        $source = "LOWER(COALESCE(utm_source, ''))";
        $referer = "LOWER(COALESCE(referer_domain, ''))";

        $in = function (string $column, array $values) use (&$bindings): string {
            array_push($bindings, ...$values);

            return $column.' IN ('.implode(', ', array_fill(0, count($values), '?')).')';
        };

        $hosts = function (array $hosts) use ($referer, &$bindings): string {
            $parts = [];
            foreach ($hosts as $host) {
                if (str_ends_with($host, '.*')) {
                    $base = substr($host, 0, -1);
                    $parts[] = "{$referer} LIKE ? OR {$referer} LIKE ?";
                    array_push($bindings, $base.'%', '%.'.$base.'%');
                } else {
                    $parts[] = "{$referer} = ? OR {$referer} LIKE ?";
                    array_push($bindings, $host, '%.'.$host);
                }
            }

            return '('.implode(' OR ', $parts).')';
        };

        $sql = 'CASE'
            .' WHEN '.$in($medium, self::PAID_MEDIUMS)." THEN 'paid'"
            .' WHEN '.$in($medium, self::EMAIL_VALUES).' OR '.$in($source, self::EMAIL_VALUES)." THEN 'email'"
            .' WHEN '.$in($medium, self::SOCIAL_MEDIUMS).' OR '.$in($source, self::SOCIAL_SOURCES).' OR '.$hosts(self::SOCIAL_HOSTS)." THEN 'social'"
            .' WHEN '.$in($medium, ['organic']).' OR '.$hosts(self::SEARCH_HOSTS)." THEN 'organic_search'"
            ." WHEN {$source} <> '' OR {$medium} <> '' THEN 'campaign'";

        $self = self::normalizeDomain($siteDomain);
        if ($self !== null) {
            $sql .= " WHEN {$referer} <> '' AND NOT ({$referer} = ? OR {$referer} LIKE ?) THEN 'referral'";
            array_push($bindings, $self, '%.'.$self);
        } else {
            $sql .= " WHEN {$referer} <> '' THEN 'referral'";
        }

        $sql .= " ELSE 'direct' END";

        return [$sql, $bindings];
    }

    /** @return array{0: string, 1: list<string>} */
    public static function refererExpression(): array
    {
        $referer = "LOWER(COALESCE(referer_domain, ''))";
        $bindings = [];
        $cases = [];

        foreach ([...self::SEARCH_HOSTS, ...self::SOCIAL_HOSTS] as $host) {
            if (str_ends_with($host, '.*')) {
                $base = substr($host, 0, -1);
                $cases[] = "WHEN {$referer} LIKE ? OR {$referer} LIKE ? THEN ?";
                array_push($bindings, $base.'%', '%.'.$base.'%', $base.'com');
            } else {
                $cases[] = "WHEN {$referer} = ? OR {$referer} LIKE ? THEN ?";
                array_push($bindings, $host, '%.'.$host, $host);
            }
        }

        $sql = 'CASE '.implode(' ', $cases)." WHEN {$referer} LIKE 'www.%' THEN SUBSTR({$referer}, 5) ELSE {$referer} END";

        return [$sql, $bindings];
    }

    public static function normalizeReferer(string $domain): string
    {
        $host = strtolower(trim($domain));

        foreach ([...self::SEARCH_HOSTS, ...self::SOCIAL_HOSTS] as $known) {
            if (str_ends_with($known, '.*')) {
                $base = substr($known, 0, -1);
                if (str_starts_with($host, $base) || str_contains($host, '.'.$base)) {
                    return $base.'com';
                }
            } elseif ($host === $known || str_ends_with($host, '.'.$known)) {
                return $known;
            }
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public static function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST) ?: $domain;
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
