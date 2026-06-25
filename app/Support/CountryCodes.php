<?php

namespace App\Support;

use Locale;

/**
 * Resolves English country names to ISO 3166-1 alpha-2 codes.
 *
 * The name→code map is built by inverting ICU's English region display names
 * (ext-intl), so it needs no hand-maintained per-country table and tracks the
 * same English names GeoLite2 emits. Used only for best-effort backfill of
 * historical rows; new clicks get their code directly from GeoIpService.
 */
class CountryCodes
{
    /**
     * Deprecated, transitional, or exceptionally-reserved alpha-2 codes that
     * ICU still resolves to a *current* country's English name (e.g. DD→"Germany",
     * FX→"France", SU→"Russia", UK→"United Kingdom"). Excluding them keeps the
     * inverted map keyed on the canonical code. This is a stable historical
     * list — it does not grow as new countries are added.
     */
    private const DEPRECATED_CODES = [
        'AN', 'BU', 'CS', 'DD', 'DY', 'FX', 'HV', 'NH', 'NQ', 'NT', 'RH', 'SU',
        'TP', 'UK', 'VD', 'YD', 'YU', 'ZR', 'EU', 'EZ', 'QU', 'UN', 'FQ', 'JT',
        'MI', 'PC', 'PU', 'PZ', 'WK', 'CT',
    ];

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

        $deny = array_flip(self::DEPRECATED_CODES);
        $map = [];

        for ($a = ord('A'); $a <= ord('Z'); $a++) {
            for ($b = ord('A'); $b <= ord('Z'); $b++) {
                $code = chr($a).chr($b);

                if (isset($deny[$code])) {
                    continue;
                }

                $name = Locale::getDisplayRegion('-'.$code, 'en');

                // ICU returns the input code for unassigned regions and
                // "Unknown Region" for ZZ; skip both.
                if ($name === $code || stripos($name, 'Unknown') !== false) {
                    continue;
                }

                $key = mb_strtolower($name);
                if (! isset($map[$key])) {
                    $map[$key] = $code;
                }
            }
        }

        return self::$map = $map;
    }
}
