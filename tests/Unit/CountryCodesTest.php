<?php

namespace Tests\Unit;

use App\Support\CountryCodes;
use PHPUnit\Framework\TestCase;

class CountryCodesTest extends TestCase
{
    public function test_maps_known_country_names_to_alpha2(): void
    {
        $this->assertSame('DE', CountryCodes::toAlpha2('Germany'));
        $this->assertSame('US', CountryCodes::toAlpha2('United States'));
        $this->assertSame('FR', CountryCodes::toAlpha2('France'));
    }

    public function test_is_case_insensitive(): void
    {
        $this->assertSame('DE', CountryCodes::toAlpha2('germany'));
    }

    public function test_returns_null_for_unknown_or_empty(): void
    {
        $this->assertNull(CountryCodes::toAlpha2('Atlantis'));
        $this->assertNull(CountryCodes::toAlpha2(null));
        $this->assertNull(CountryCodes::toAlpha2(''));
    }
}
