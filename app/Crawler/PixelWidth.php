<?php

namespace App\Crawler;

class PixelWidth
{
    private const DEFAULT_WIDTH = 10;

    /** Approximate Arial advance widths (px) at the reference title font size. */
    private const WIDTHS = [
        ' ' => 5,
        'a' => 10, 'b' => 10, 'c' => 9, 'd' => 10, 'e' => 10, 'f' => 5, 'g' => 10,
        'h' => 10, 'i' => 4, 'j' => 4, 'k' => 9, 'l' => 4, 'm' => 15, 'n' => 10,
        'o' => 10, 'p' => 10, 'q' => 10, 'r' => 6, 's' => 9, 't' => 5, 'u' => 10,
        'v' => 9, 'w' => 13, 'x' => 9, 'y' => 9, 'z' => 9,
        'A' => 12, 'B' => 12, 'C' => 13, 'D' => 13, 'E' => 12, 'F' => 11, 'G' => 14,
        'H' => 13, 'I' => 5, 'J' => 9, 'K' => 12, 'L' => 10, 'M' => 15, 'N' => 13,
        'O' => 14, 'P' => 12, 'Q' => 14, 'R' => 13, 'S' => 12, 'T' => 11, 'U' => 13,
        'V' => 12, 'W' => 17, 'X' => 12, 'Y' => 12, 'Z' => 11,
        '0' => 10, '1' => 10, '2' => 10, '3' => 10, '4' => 10, '5' => 10, '6' => 10,
        '7' => 10, '8' => 10, '9' => 10,
        '.' => 5, ',' => 5, ':' => 5, ';' => 5, '!' => 5, '?' => 10, '-' => 6,
        '_' => 10, '(' => 6, ')' => 6, '/' => 5, '|' => 5, "'" => 4, '"' => 7,
        '&' => 12, '@' => 18, '*' => 7, '+' => 11, '=' => 11, '#' => 10, '%' => 15,
    ];

    /**
     * Estimated rendered width in pixels. $scale adjusts for font size (title 1.0,
     * snippet/description ~0.72). Approximate — a calibrated char table, not real rendering.
     */
    public static function widthPx(string $text, float $scale = 1.0): int
    {
        $sum = 0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $sum += self::WIDTHS[$char] ?? self::DEFAULT_WIDTH;
        }

        return (int) round($sum * $scale);
    }
}
