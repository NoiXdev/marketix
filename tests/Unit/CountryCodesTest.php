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
        $this->assertSame('GB', CountryCodes::toAlpha2('United Kingdom'));
        $this->assertSame('RU', CountryCodes::toAlpha2('Russia'));
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

    public function test_resolves_common_alias_names(): void
    {
        $this->assertSame('CZ', CountryCodes::toAlpha2('Czech Republic'));
        $this->assertSame('TR', CountryCodes::toAlpha2('Turkey'));
        $this->assertSame('HK', CountryCodes::toAlpha2('Hong Kong'));
        $this->assertSame('MO', CountryCodes::toAlpha2('Macao'));
        $this->assertSame('CI', CountryCodes::toAlpha2('Ivory Coast'));
        $this->assertSame('MM', CountryCodes::toAlpha2('Myanmar'));
        $this->assertSame('CD', CountryCodes::toAlpha2('Democratic Republic of the Congo'));
    }
}
