<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class VisitorHash
{
    /**
     * Anonymized per-visitor identifier for a single calendar day.
     *
     * The full IP is hashed together with the user agent and a random daily
     * salt that expires after 48h. Once the salt is gone the hash can no longer
     * be linked back to an IP, so stored hashes are anonymous, not merely
     * pseudonymous. The scope (a site, or a project for link clicks) keeps the
     * same visitor from being linked across sites and projects.
     */
    public static function for(?string $ip, string $userAgent, string $scope): string
    {
        return hash('sha256', ($ip ?? '').'|'.$userAgent.'|'.$scope.'|'.self::dailySalt());
    }

    /**
     * Identifier for a visitor recognised by the snippet's first-party id,
     * scoped to the site so the same id cannot be linked across sites.
     */
    public static function forVisitorId(string $visitorId, string $siteId): string
    {
        return hash('sha256', $visitorId.'|'.$siteId);
    }

    private static function dailySalt(): string
    {
        return Cache::remember(
            'visitor_salt:'.now()->format('Y-m-d'),
            now()->addHours(48),
            fn () => bin2hex(random_bytes(32)),
        );
    }
}
