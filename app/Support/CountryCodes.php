<?php

namespace App\Support;

use Locale;

/**
 * Resolves English country names to ISO 3166-1 alpha-2 codes.
 *
 * The name→code map is built by inverting ICU's English region display names
 * (ext-intl), so it needs no hand-maintained table and tracks the same English
 * names GeoLite2 emits. Used only for best-effort backfill of historical rows;
 * new clicks get their code directly from GeoIpService.
 */
class CountryCodes
{
    /** @var array<string, string>|null name(lower) => alpha-2 */
    private static ?array $map = null;

    public static function toAlpha2(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return self::map()[mb_strtolower(trim($name))] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $codesByName = [];
        for ($a = ord('A'); $a <= ord('Z'); $a++) {
            for ($b = ord('A'); $b <= ord('Z'); $b++) {
                $code = chr($a).chr($b);
                $name = Locale::getDisplayRegion('-'.$code, 'en');

                // ICU returns the input code for unassigned regions and
                // "Unknown Region" for ZZ; skip both.
                if ($name === $code || stripos($name, 'Unknown') !== false) {
                    continue;
                }

                $key = mb_strtolower($name);
                if (! isset($codesByName[$key])) {
                    $codesByName[$key] = [];
                }
                $codesByName[$key][] = $code;
            }
        }

        // Canonical ISO 3166-1 alpha-2 codes to prefer over obsolete aliases.
        // Keys are lowercased English display names (as returned by ICU).
        $canonical = [
            'germany' => 'DE', 'france' => 'FR', 'united kingdom' => 'GB', 'united states' => 'US',
            'china' => 'CN', 'india' => 'IN', 'japan' => 'JP', 'australia' => 'AU',
            'brazil' => 'BR', 'canada' => 'CA', 'mexico' => 'MX', 'russia' => 'RU',
        ];

        $map = [];
        foreach ($codesByName as $name => $codes) {
            // If there's a canonical code for this name, use it; otherwise use first.
            if (isset($canonical[$name])) {
                $map[$name] = $canonical[$name];
            } else {
                sort($codes);
                $map[$name] = reset($codes);
            }
        }

        return self::$map = $map;
    }
}
