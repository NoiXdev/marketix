<?php

namespace App\Support;

class Locales
{
    /** @return list<array{code:string,label:string}> */
    public static function all(): array
    {
        return config('app_locales');
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    public static function isSupported(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }

    public static function default(): string
    {
        return (string) config('app.fallback_locale', 'en');
    }
}
