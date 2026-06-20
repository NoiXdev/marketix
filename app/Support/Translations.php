<?php

namespace App\Support;

class Translations
{
    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    /**
     * Active locale's translation catalog (filename => array), deep-merged
     * over English so any missing key renders the English string.
     *
     * @return array<string,mixed>
     */
    public static function forLocale(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $base = self::load(Locales::default());
        $catalog = $locale === Locales::default()
            ? $base
            : self::deepMerge($base, self::load($locale));

        return self::$cache[$locale] = $catalog;
    }

    /** @return array<string,mixed> */
    private static function load(string $locale): array
    {
        $dir = lang_path($locale);
        if (! is_dir($dir)) {
            return [];
        }

        $catalog = [];
        foreach (glob($dir.'/*.php') as $file) {
            $namespace = basename($file, '.php');
            $catalog[$namespace] = require $file;
        }

        return $catalog;
    }

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $override
     * @return array<string,mixed>
     */
    private static function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
