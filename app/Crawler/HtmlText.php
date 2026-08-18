<?php

namespace App\Crawler;

class HtmlText
{
    /** Visible-text length of an HTML fragment (scripts/styles removed). */
    public static function visibleLength(string $html): int
    {
        $withoutScripts = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($withoutScripts)));

        return mb_strlen($text);
    }
}
