<?php

namespace App\Crawler;

class UrlSafety
{
    /**
     * Resolve a hostname (or literal IP) to its IP addresses.
     *
     * @return string[]
     */
    public static function resolveIps(string $host): array
    {
        return @gethostbynamel($host) ?: (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : []);
    }

    /**
     * Whether every given IP is a public, routable address (no private/reserved/loopback ranges).
     *
     * @param  string[]  $ips
     */
    public static function ipsAreSafe(array $ips): bool
    {
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a host is safe to crawl or follow a redirect to: not empty/localhost,
     * resolves to at least one IP, and every resolved IP is public.
     */
    public static function hostIsSafe(string $host): bool
    {
        if ($host === '' || strtolower($host) === 'localhost') {
            return false;
        }

        return self::ipsAreSafe(self::resolveIps($host));
    }
}
