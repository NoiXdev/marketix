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
     * pseudonymous.
     */
    public static function for(?string $ip, string $userAgent): string
    {
        return hash('sha256', ($ip ?? '').'|'.$userAgent.'|'.self::dailySalt());
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
