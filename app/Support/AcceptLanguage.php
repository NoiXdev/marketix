<?php

namespace App\Support;

class AcceptLanguage
{
    public static function primary(?string $header): ?string
    {
        $tag = strtolower(trim(strtok((string) $header, ',;-_') ?: ''));

        return preg_match('/^[a-z]{2,3}$/', $tag) === 1 ? $tag : null;
    }
}
