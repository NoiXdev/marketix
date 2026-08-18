<?php

namespace Tests\Unit\Crawler;

use App\Crawler\SchemaRules;
use PHPUnit\Framework\TestCase;

class SchemaRulesTest extends TestCase
{
    public function test_known_type_product(): void
    {
        $this->assertSame(['name'], SchemaRules::requiredFor('Product'));
    }

    public function test_known_type_event(): void
    {
        $this->assertSame(['name', 'startDate'], SchemaRules::requiredFor('Event'));
    }

    public function test_known_type_breadcrumb_list(): void
    {
        $this->assertSame(['itemListElement'], SchemaRules::requiredFor('BreadcrumbList'));
    }

    public function test_known_type_image_object_uses_alternation_token(): void
    {
        $this->assertSame(['contentUrl|url'], SchemaRules::requiredFor('ImageObject'));
    }

    public function test_unknown_type_returns_empty_array(): void
    {
        $this->assertSame([], SchemaRules::requiredFor('Frobnicate'));
    }

    public function test_type_match_is_case_sensitive(): void
    {
        $this->assertSame([], SchemaRules::requiredFor('product'));
    }

    public function test_web_page_has_no_required_fields(): void
    {
        $this->assertSame([], SchemaRules::requiredFor('WebPage'));
    }
}
