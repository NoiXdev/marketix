<?php

namespace Tests\Unit;

use App\Support\Locales;
use Tests\TestCase;

class LocalesTest extends TestCase
{
    public function test_codes_lists_supported_locales(): void
    {
        $this->assertSame(['en', 'de', 'nl', 'fr'], Locales::codes());
    }

    public function test_is_supported(): void
    {
        $this->assertTrue(Locales::isSupported('de'));
        $this->assertFalse(Locales::isSupported('es'));
    }

    public function test_all_has_labels(): void
    {
        $this->assertcontains('Deutsch', array_column(Locales::all(), 'label'));
    }
}
