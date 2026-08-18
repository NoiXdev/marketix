<?php

namespace Tests\Unit\Crawler;

use App\Crawler\HtmlText;
use Tests\TestCase;

class HtmlTextTest extends TestCase
{
    public function test_excludes_script_content(): void
    {
        $length = HtmlText::visibleLength('<body><script>var x=123456789;</script>Hi</body>');

        $this->assertSame(2, $length);
    }

    public function test_excludes_style_and_noscript_content(): void
    {
        $html = '<div><style>.a{color:red}</style><noscript>no js here</noscript>Hi</div>';

        $this->assertSame(2, HtmlText::visibleLength($html));
    }

    public function test_long_paragraph_returns_its_text_length(): void
    {
        $text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt.';
        $html = '<p>'.$text.'</p>';

        $this->assertSame(mb_strlen($text), HtmlText::visibleLength($html));
    }

    public function test_collapses_whitespace_between_tags(): void
    {
        $html = "<div>\n  Hello   \n\n   World  \n</div>";

        $this->assertSame(mb_strlen('Hello World'), HtmlText::visibleLength($html));
    }
}
