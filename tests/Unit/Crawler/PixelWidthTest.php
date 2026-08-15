<?php

namespace Tests\Unit\Crawler;

use App\Crawler\PixelWidth;
use PHPUnit\Framework\TestCase;

class PixelWidthTest extends TestCase
{
    public function test_longer_string_is_wider(): void
    {
        $this->assertGreaterThan(
            PixelWidth::widthPx('short'),
            PixelWidth::widthPx('a considerably longer string of text'),
        );
    }

    public function test_long_title_exceeds_561px(): void
    {
        $this->assertGreaterThan(561, PixelWidth::widthPx(str_repeat('a', 70)));
    }

    public function test_short_title_below_200px(): void
    {
        $this->assertLessThan(200, PixelWidth::widthPx('Home'));
    }

    public function test_scale_multiplies(): void
    {
        $full = PixelWidth::widthPx('hello world this is a description');
        $scaled = PixelWidth::widthPx('hello world this is a description', 0.72);
        $this->assertLessThan($full, $scaled);
        $this->assertEqualsWithDelta($full * 0.72, $scaled, 1.0);
    }

    public function test_non_ascii_does_not_error_and_uses_default(): void
    {
        $this->assertGreaterThan(0, PixelWidth::widthPx('café ñ 日本語'));
    }
}
