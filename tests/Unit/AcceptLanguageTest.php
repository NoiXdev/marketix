<?php

namespace Tests\Unit;

use App\Support\AcceptLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AcceptLanguageTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function headers(): array
    {
        return [
            'region' => ['de-CH,de;q=0.9,en;q=0.8', 'de'],
            'plain' => ['fr', 'fr'],
            'three letters' => ['gsw-CH,de;q=0.9', 'gsw'],
            'filipino is not finnish' => ['fil-PH', 'fil'],
            'upper case and underscore' => ['EN_us', 'en'],
            'quality only' => ['it;q=0.8', 'it'],
            'wildcard' => ['*', null],
            'empty' => ['', null],
            'missing' => [null, null],
            'garbage' => ['x-klingon', null],
        ];
    }

    #[DataProvider('headers')]
    public function test_primary_language_subtag(?string $header, ?string $expected): void
    {
        $this->assertSame($expected, AcceptLanguage::primary($header));
    }
}
