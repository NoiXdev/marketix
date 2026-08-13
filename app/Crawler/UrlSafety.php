<?php

namespace App\Crawler;

class UrlSafety
{
    /**
     * Resolve a hostname (or literal IP) to its IP addresses, including IPv6.
     *
     * @return string[]
     */
    public static function resolveIps(string $host): array
    {
        // IPv6 literal hosts arrive bracketed from parse_url, e.g. "[::1]".
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        // gethostbynamel only returns A (IPv4) records. Also resolve AAAA (IPv6),
        // otherwise a host that only publishes an IPv6 address (e.g. pointing at
        // ::1 or an IPv4-mapped private address) would bypass the range checks.
        $ips = @gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
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
