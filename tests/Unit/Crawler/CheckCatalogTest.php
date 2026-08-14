<?php

namespace Tests\Unit\Crawler;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
use App\Crawler\IssueCode;
use PHPUnit\Framework\TestCase;

class CheckCatalogTest extends TestCase
{
    public function test_every_entry_is_well_formed(): void
    {
        $categories = array_map(fn ($c) => $c->value, IssueCategory::cases());

        foreach (CheckCatalog::all() as $entry) {
            $this->assertArrayHasKey('code', $entry);
            $this->assertContains($entry['category'], $categories, $entry['code']);
            $this->assertContains($entry['severity'], ['error', 'warning', 'notice'], $entry['code']);
            $this->assertContains($entry['status'], ['active', 'planned'], $entry['code']);
        }
    }

    public function test_codes_are_unique(): void
    {
        $codes = array_column(CheckCatalog::all(), 'code');
        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_every_issue_code_is_an_active_catalogue_entry(): void
    {
        $active = CheckCatalog::activeCodes();

        foreach (IssueCode::cases() as $case) {
            $this->assertContains($case->value, $active, "IssueCode {$case->value} must be an active catalogue check");
            // category() agrees with the catalogue
            $this->assertSame(CheckCatalog::categoryOf($case->value), $case->category()->value, $case->value);
        }
    }

    public function test_active_codes_are_all_real_issue_codes(): void
    {
        foreach (CheckCatalog::all() as $entry) {
            if ($entry['status'] === 'active') {
                $this->assertNotNull(IssueCode::tryFrom($entry['code']), "active code {$entry['code']} has no IssueCode case");
            }
        }
    }

    public function test_active_codes_for_category_filters(): void
    {
        $this->assertContains('missing_title', CheckCatalog::activeCodesForCategory('page_title'));
        $this->assertSame([], CheckCatalog::activeCodesForCategory('security')); // all planned in Phase 0
    }
}
