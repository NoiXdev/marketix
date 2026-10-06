<?php

namespace Tests\Unit;

use App\Support\UtmTagger;
use PHPUnit\Framework\TestCase;

class UtmTaggerTest extends TestCase
{
    public function test_normalize_trims_drops_blanks_and_unknown_keys(): void
    {
        $this->assertSame(
            ['source' => 'newsletter', 'campaign' => 'autumn sale'],
            UtmTagger::normalize(['campaign' => ' autumn sale ', 'source' => 'newsletter', 'medium' => '  ', 'term' => null, 'evil' => 'x']),
        );
        $this->assertNull(UtmTagger::normalize(['source' => '', 'medium' => null]));
        $this->assertNull(UtmTagger::normalize(null));
    }

    public function test_appends_parameters_in_a_fixed_order(): void
    {
        $this->assertSame(
            'https://example.com/landing?utm_source=qr&utm_medium=print&utm_campaign=autumn%20sale',
            UtmTagger::apply('https://example.com/landing', ['campaign' => 'autumn sale', 'medium' => 'print', 'source' => 'qr']),
        );
    }

    public function test_keeps_existing_query_and_fragment_and_never_overrides_explicit_tags(): void
    {
        $this->assertSame(
            'https://example.com/p?a=1&b=%2F&utm_source=hand-tagged&utm_medium=print#section',
            UtmTagger::apply('https://example.com/p?a=1&b=%2F&utm_source=hand-tagged#section', ['source' => 'qr', 'medium' => 'print']),
        );
        $this->assertSame('https://example.com/?x=1&utm_source=qr', UtmTagger::apply('https://example.com/?x=1&', ['source' => 'qr']));
        $this->assertSame('https://example.com/?utm_source=a', UtmTagger::apply('https://example.com/?utm_source=a', ['source' => 'qr']));
    }

    public function test_leaves_non_http_targets_and_empty_tags_untouched(): void
    {
        $this->assertSame('mailto:hi@example.com', UtmTagger::apply('mailto:hi@example.com', ['source' => 'qr']));
        $this->assertSame('tel:+41441234567', UtmTagger::apply('tel:+41441234567', ['source' => 'qr']));
        $this->assertSame('https://example.com/', UtmTagger::apply('https://example.com/', null));
        $this->assertSame('https://example.com/', UtmTagger::apply('https://example.com/', ['source' => ' ']));
    }
}
